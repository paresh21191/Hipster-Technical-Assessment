<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Upload;
use App\Models\Image;
use Illuminate\Support\Facades\DB;

class AttachImageToProducts extends Command
{
    protected $signature = 'products:attach-image {upload_identifier}';
    protected $description = 'Attach primary image from upload to all products';

    public function handle()
    {
        $uploadIdentifier = $this->argument('upload_identifier');

        $upload = Upload::where('upload_identifier', $uploadIdentifier)->where('completed', true)->first();
        if (!$upload) {
            $this->error("Upload with identifier {$uploadIdentifier} not found or incomplete.");
            return 1;
        }

        $originalImage = $upload->images()->where('variant', 'original')->first();
        if (!$originalImage) {
            $this->error("Original image not found for upload {$uploadIdentifier}.");
            return 1;
        }

        $products = Product::lockForUpdate()->get();

        DB::beginTransaction();
        try {
            foreach ($products as $product) {
                // Idempotent: don't update if already set
                if ($product->primary_image_id !== $originalImage->id) {
                    $product->primary_image_id = $originalImage->id;
                    $product->save();
                }
            }
            DB::commit();
            $this->info("Primary image attached to all products successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}