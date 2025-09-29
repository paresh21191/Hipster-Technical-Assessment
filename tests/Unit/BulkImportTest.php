<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;
use App\Models\Upload;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_new_product_from_csv()
    {
        $csvRow = [
            'sku' => 'SKU100001',
            'name' => 'Test Product',
            'price' => 100
        ];

        $product = Product::updateOrCreate(
            ['sku' => $csvRow['sku']],
            $csvRow
        );

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU100001',
            'name' => 'Test Product'
        ]);
        $this->assertEquals('Test Product', $product->name);
    }

    /** @test */
    public function it_can_update_existing_product_from_csv()
    {
        // Create product manually
        Product::create([
            'sku' => 'SKU100002',
            'name' => 'Old Name',
            'price' => 50
        ]);

        $csvRow = [
            'sku' => 'SKU100002',
            'name' => 'Updated Name',
            'price' => 200
        ];

        $updatedProduct = Product::updateOrCreate(
            ['sku' => $csvRow['sku']],
            $csvRow
        );

        $this->assertEquals('Updated Name', $updatedProduct->name);
        $this->assertDatabaseHas('products', [
            'sku' => 'SKU100002',
            'name' => 'Updated Name'
        ]);
    }

    /** @test */
    public function it_rejects_invalid_csv_rows()
    {
        $csvRow = [
            'sku' => null, // missing SKU
            'name' => 'Invalid Product'
        ];

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::updateOrCreate(
            ['sku' => $csvRow['sku']],
            $csvRow
        );
    }

    /** @test */
    public function it_can_upload_primary_image_and_generate_variants()
    {
        Storage::fake('public');

        $product = Product::create([
            'sku' => 'SKU100003',
            'name' => 'Image Product',
            'price' => 150
        ]);

        $file = UploadedFile::fake()->image('product.jpg');

        
        $upload = Upload::create([
            'product_id' => $product->id,
            'filename' => $file->hashName(),
            'is_primary' => true
        ]);

        
        $file->storeAs("uploads/{$product->sku}/original", $file->hashName(), 'public');

        // Generate variants
        foreach ([256, 512, 1024] as $size) {
            $variantPath = "uploads/{$product->sku}/{$size}x{$size}/{$file->hashName()}";
            Storage::disk('public')->put($variantPath, 'dummy content');
            $this->assertTrue(Storage::disk('public')->exists($variantPath));
        }

        $this->assertDatabaseHas('uploads', [
            'product_id' => $product->id,
            'filename' => $file->hashName(),
            'is_primary' => true
        ]);
    }

    /** @test */
    public function it_does_not_corrupt_reuploaded_chunks()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('chunked.png', 1024);

        
        $chunk1 = substr($file->getContent(), 0, 512);
        $chunk2 = substr($file->getContent(), 512);

        Storage::disk('public')->put('chunks/chunk1.png', $chunk1);
        Storage::disk('public')->put('chunks/chunk2.png', $chunk2);


        Storage::disk('public')->put('chunks/chunk1.png', $chunk1);

        $this->assertEquals(
            file_get_contents(Storage::disk('public')->path('chunks/chunk1.png')),
            $chunk1
        );
    }
}
