<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
    }

    public function handle()
    {
        $import = ProductImport::find($this->importId);
        if (!$import) {
            Log::error('ImportProductsJob: import record not found', ['id' => $this->importId]);
            return;
        }

        // mark processing
        $import->status = 'processing';
        $import->save();

        // Determine disk and read file
        $diskName = config('filesystems.disks.private') ? 'private' : config('filesystems.default');
        $disk = Storage::disk($diskName);

        if (!$disk->exists($import->path)) {
            // try absolute path
            $abs = storage_path('app/' . ltrim($import->path, '/'));
            if (!file_exists($abs)) {
                $import->status = 'failed';
                $import->errors = ['file' => 'not_found'];
                $import->save();
                Log::error('ImportProductsJob: file not found', ['path' => $import->path]);
                return;
            }
            $fileContent = file_get_contents($abs);
            $temp = sys_get_temp_dir() . '/import_products_' . uniqid() . '.csv';
            file_put_contents($temp, $fileContent);
            $sheets = Excel::toArray(null, $temp);
            @unlink($temp);
        } else {
            $temp = sys_get_temp_dir() . '/import_products_' . uniqid() . '.csv';
            file_put_contents($temp, $disk->get($import->path));
            $sheets = Excel::toArray(null, $temp);
            @unlink($temp);
        }

        if (empty($sheets) || empty($sheets[0])) {
            $import->status = 'failed';
            $import->errors = ['file' => 'empty'];
            $import->save();
            Log::warning('ImportProductsJob: empty file', ['path' => $import->path]);
            return;
        }

        $rows = $sheets[0];

        // detect header
        $header = array_map(fn($h) => strtolower(trim($h)), $rows[0]);
        $startIndex = 1;

        $results = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            $data = [];
            foreach ($header as $colIndex => $colName) {
                $data[$colName] = $row[$colIndex] ?? null;
            }

            $results['processed']++;

            try {
                DB::transaction(function () use ($data, &$results, $i, $import) {
                    // require sku and name and price
                    $sku = $data['sku'] ?? null;
                    $name = $data['name'] ?? null;
                    $price = isset($data['price']) ? (float)$data['price'] : null;

                    if (!$sku || !$name || $price === null) {
                        throw new \Exception('Missing required fields (sku,name,price)');
                    }

                    $payload = [
                        'name' => $name,
                        'sku' => $sku,
                        'description' => $data['description'] ?? null,
                        'price' => $price,
                        'cost' => isset($data['cost']) ? (float)$data['cost'] : null,
                        'quantity' => isset($data['quantity']) ? (int)$data['quantity'] : null,
                        'low_stock_threshold' => isset($data['low_stock_threshold']) ? (int)$data['low_stock_threshold'] : null,
                    ];

                    // set vendor if provided
                    if (!empty($data['vendor_email'])) {
                        $vendor = \App\Models\User::where('email', $data['vendor_email'])->first();
                        if ($vendor) {
                            $payload['vendor_id'] = $vendor->id;
                        }
                    } elseif (!empty($data['vendor_id'])) {
                        $payload['vendor_id'] = (int)$data['vendor_id'];
                    }

                    // idempotent upsert by sku
                    $product = Product::updateOrCreate(['sku' => $sku], array_filter($payload, fn($v) => $v !== null));

                    // track counts
                    if ($product->wasRecentlyCreated ?? false) {
                        $results['created']++;
                    } else {
                        $results['updated']++;
                    }

                    // handle variants (dedupe by sku or attributes)
                    if (!empty($data['variants'])) {
                        $variants = json_decode($data['variants'], true);
                        if (is_array($variants)) {
                            foreach ($variants as $v) {
                                $variantSku = $v['sku'] ?? null;
                                $attributes = $v['attributes'] ?? null;

                                if ($variantSku) {
                                    $variant = ProductVariant::firstOrNew(['sku' => $variantSku]);
                                    $variant->product_id = $product->id;
                                    $variant->name = $v['name'] ?? $variant->name;
                                    $variant->attributes = $attributes ?? $variant->attributes;
                                    $variant->price = isset($v['price']) ? (float)$v['price'] : ($variant->price ?? $product->price);
                                    $variant->quantity = isset($v['quantity']) ? (int)$v['quantity'] : ($variant->quantity ?? ($product->quantity ?? 0));
                                    $variant->save();
                                } else {
                                    // dedupe by attributes if no sku
                                    $q = ProductVariant::where('product_id', $product->id);
                                    if ($attributes) {
                                        $q->where('attributes', json_encode($attributes));
                                    }
                                    $variant = $q->first();
                                    if ($variant) {
                                        $variant->update([
                                            'name' => $v['name'] ?? $variant->name,
                                            'price' => isset($v['price']) ? (float)$v['price'] : $variant->price,
                                            'quantity' => isset($v['quantity']) ? (int)$v['quantity'] : $variant->quantity,
                                        ]);
                                    } else {
                                        ProductVariant::create([
                                            'product_id' => $product->id,
                                            'sku' => $product->sku . '-' . uniqid(),
                                            'name' => $v['name'] ?? null,
                                            'attributes' => $attributes,
                                            'price' => isset($v['price']) ? (float)$v['price'] : $product->price,
                                            'quantity' => isset($v['quantity']) ? (int)$v['quantity'] : ($product->quantity ?? 0),
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = ['row' => $i + 1, 'message' => $e->getMessage()];
                Log::error('ImportProductsJob row failed', ['row' => $i + 1, 'error' => $e->getMessage()]);
            }
        }

        // write results next to file on same disk
        $resultPath = preg_replace('/\.csv$/i', '', $import->path) . '-results.json';
        try {
            if ($disk->exists($import->path)) {
                $disk->put($resultPath, json_encode($results, JSON_PRETTY_PRINT));
                $import->results_path = $resultPath;
            } else {
                // write to storage/app
                Storage::put($resultPath, json_encode($results, JSON_PRETTY_PRINT));
                $import->results_path = $resultPath;
            }
        } catch (\Throwable $e) {
            Log::warning('ImportProductsJob could not write results', ['error' => $e->getMessage()]);
        }

        $import->status = $results['failed'] > 0 ? 'failed' : 'completed';
        $import->errors = $results['errors'] ?: null;
        $import->save();
    }
}
