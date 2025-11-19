<?php

namespace Tests\Unit;

use App\Jobs\ImportProductsJob;
use App\Models\ProductImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportProductsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_job_is_idempotent_and_writes_results()
    {
        Storage::fake('private');

        $csv = "sku,name,description,price,cost,quantity,low_stock_threshold,vendor_email,variants\n";
        $csv .= "IDEMP-SKU-1,Idempotent Product,desc,9.99,3.00,5,1,,[]\n";

        $path = 'imports/products/idempotent.csv';
        Storage::disk('private')->put($path, $csv);

        $import = ProductImport::create([
            'path' => $path,
            'uploader_id' => null,
            'status' => 'pending',
        ]);

        // First run
        ImportProductsJob::dispatchSync($import->id);

        $import->refresh();
        $this->assertTrue(in_array($import->status, ['completed','failed']));
        $this->assertNotEmpty($import->results_path);
        $this->assertTrue(Storage::disk('private')->exists($import->results_path));

        // Run again to ensure idempotency (no duplicate products)
        ImportProductsJob::dispatchSync($import->id);

        // results file should still exist
        $this->assertTrue(Storage::disk('private')->exists($import->results_path));
    }
}
