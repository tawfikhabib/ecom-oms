<?php

namespace Tests\Feature\Product;

use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GetImportStatusTest extends TestCase
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

    public function test_get_import_status_returns_results()
    {
        Storage::fake('private');

        $results = [
            'processed' => 1,
            'created' => 1,
            'updated' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $path = 'imports/products/sample.csv';
        $resultsPath = 'imports/products/sample-results.json';

        // create import record
        $import = ProductImport::create([
            'path' => $path,
            'uploader_id' => $this->vendor->id,
            'status' => 'completed',
            'results_path' => $resultsPath,
        ]);

        // write results to fake disk
        Storage::disk('private')->put($resultsPath, json_encode($results, JSON_PRETTY_PRINT));

        $response = $this->get("/api/v1/imports/{$import->id}", [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $import->id);
        $response->assertJsonPath('data.status', 'completed');
        $this->assertEquals($results, $response->json('data.results'));
    }
}
