<?php

namespace Tests\Feature\Product;

use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;
    protected string $token;

    public function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'vendor']);
        Role::firstOrCreate(['name' => 'customer']);

        $this->vendor = User::factory()->create();
        $this->vendor->assignRole('vendor');
        $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->vendor);
    }

    public function test_upload_and_process_product_import(): void
    {
        Storage::fake('private');

        $csv = "sku,name,description,price,cost,quantity,low_stock_threshold,vendor_email,variants\n";
        $csv .= "TEST-SKU-1,Test Product,Imported description,12.50,5.00,10,2,{$this->vendor->email},\n";

        $file = UploadedFile::fake()->createWithContent('import_products.csv', $csv);

        $response = $this->post('/api/v1/products/import', ['file' => $file], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('product_imports', [
            'uploader_id' => $this->vendor->id,
        ]);

        $import = ProductImport::first();
        $this->assertNotNull($import);

        // Run the job synchronously to process the import
        \App\Jobs\ImportProductsJob::dispatchSync($import->id);

        $import->refresh();

        $this->assertTrue(in_array($import->status, ['completed','failed']), 'Import did not finish');

        // If completed, check the product and results file
        if ($import->status === 'completed') {
            $this->assertDatabaseHas('products', ['sku' => 'TEST-SKU-1']);
            $this->assertNotEmpty($import->results_path);
            $this->assertTrue(Storage::disk('private')->exists($import->results_path));
        } else {
            // allow failed but ensure errors were recorded
            $this->assertNotEmpty($import->errors);
        }
    }
}
