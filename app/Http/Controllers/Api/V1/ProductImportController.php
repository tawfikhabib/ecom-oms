<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImport;
use App\Jobs\ImportProductsJob;

class ProductImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        // store under private imports/products if private disk configured
        $disk = config('filesystems.disks.private') ? 'private' : config('filesystems.default');
        $path = $request->file('file')->store('imports/products', $disk);

        // create import record
        $import = ProductImport::create([
            'path' => $path,
            'uploader_id' => $request->user()?->id ?? null,
            'status' => 'pending',
        ]);

        // dispatch job with import id
        ImportProductsJob::dispatch($import->id);

        return response()->json([
            'message' => 'Product import queued',
            'data' => ['id' => $import->id, 'path' => $path],
        ], 202);
    }

    /**
     * Show import status and results (if available)
     *
     * @param ProductImport $import
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(ProductImport $import)
    {
        // allow only uploader or admins to view
        $user = auth()->user();
        if ($user && $user->hasRole('vendor') && $import->uploader_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $diskName = config('filesystems.disks.private') ? 'private' : config('filesystems.default');
        $disk = Storage::disk($diskName);

        $results = null;
        if ($import->results_path) {
            try {
                if ($disk->exists($import->results_path)) {
                    $contents = $disk->get($import->results_path);
                } else {
                    $abs = storage_path('app/' . ltrim($import->results_path, '/'));
                    $contents = file_exists($abs) ? file_get_contents($abs) : null;
                }
                if ($contents) {
                    $results = json_decode($contents, true);
                }
            } catch (\Throwable $e) {
                // ignore read errors, surface null results
                $results = null;
            }
        }

        return response()->json([
            'data' => [
                'id' => $import->id,
                'path' => $import->path,
                'status' => $import->status,
                'results' => $results,
                'errors' => $import->errors,
                'created_at' => $import->created_at,
                'updated_at' => $import->updated_at,
            ],
        ], 200);
    }
}
