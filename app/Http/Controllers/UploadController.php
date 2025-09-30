<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;

class UploadController extends Controller
{
    protected $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'public');
    }

    /**
     * Initialize upload session
     */
    public function init(Request $request)
    {
        $request->validate([
            'upload_identifier' => 'required|string',
            'filename'          => 'required|string',
            'product_sku'       => 'required|string',
            'checksum'          => 'nullable|string',
        ]);

        $product = Product::where('sku', $request->product_sku)->first();
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $upload = Upload::where('upload_identifier', $request->upload_identifier)
            ->lockForUpdate()
            ->first();

        if (!$upload) {
            $upload = Upload::create([
                'upload_identifier' => $request->upload_identifier,
                'filename'          => $request->filename,
                'checksum'          => $request->checksum,
                'completed'         => false,
                'product_id'        => $product->id,
            ]);
        } elseif ($upload->completed) {
            return response()->json(['message' => 'Upload already completed'], 200);
        }

        Storage::disk($this->disk)->makeDirectory("uploads/{$request->upload_identifier}/chunks");

        return response()->json(['message' => 'Upload initialized']);
    }

    /**
     * Upload and resize product images
     */
    public function upload(Request $request)
    {
        $request->validate([
            'product_images'   => 'required',
            'product_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $uploadedImagePaths = [];

        if ($request->hasFile('product_images')) {
            $files = (array) $request->file('product_images');

            foreach ($files as $file) {
                // Save original
                $originalPath = $file->store('products/original', $this->disk);

                // Directory for resized versions
                $resizedDir = 'products/resized';
                Storage::disk($this->disk)->makeDirectory($resizedDir);

                $variants = [
                    '256px'  => 256,
                    '512px'  => 512,
                    '1024px' => 1024,
                ];

                foreach ($variants as $variant => $width) {
                    $resizedPath = $resizedDir . '/' . $variant . '_' . basename($originalPath);

                    if ($this->disk === 's3') {
                        // On S3: work with file contents
                        $image = Image::make(Storage::disk($this->disk)->get($originalPath))
                            ->resize($width, null, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });

                        // ✅ no ACLs
                        Storage::disk($this->disk)->put($resizedPath, (string) $image->encode());
                    } else {
                        // On local: work with file path
                        $image = Image::make(Storage::disk($this->disk)->path($originalPath))
                            ->resize($width, null, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });

                        $image->save(Storage::disk($this->disk)->path($resizedPath));
                    }

                    $uploadedImagePaths[$variant] = $resizedPath;
                }

                $uploadedImagePaths['original'] = $originalPath;
            }
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images'  => $uploadedImagePaths,
        ]);
    }

    /**
     * Upload a single chunk
     */
    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_identifier' => 'required|string',
            'chunk_index'       => 'required|integer|min:0',
            'chunk_data'        => 'required|string',
        ]);

        $upload = Upload::where('upload_identifier', $request->upload_identifier)
            ->lockForUpdate()
            ->first();

        if (!$upload || $upload->completed) {
            return response()->json(['error' => 'Upload not found or already completed'], 404);
        }

        $chunkData = base64_decode($request->chunk_data);
        if ($chunkData === false) {
            return response()->json(['error' => 'Invalid chunk data'], 400);
        }

        $chunkPath = "uploads/{$request->upload_identifier}/chunks/{$request->chunk_index}.chunk";
        // ✅ no ACLs
        Storage::disk($this->disk)->put($chunkPath, $chunkData);

        return response()->json(['message' => 'Chunk saved']);
    }

    /**
     * Complete upload and assemble chunks
     */
    public function complete(Request $request)
    {
        $request->validate([
            'upload_identifier' => 'required|string',
            'total_chunks'      => 'required|integer|min:1',
            'checksum'          => 'required|string',
        ]);

        $upload = Upload::where('upload_identifier', $request->upload_identifier)
            ->lockForUpdate()
            ->first();

        if (!$upload || $upload->completed) {
            return response()->json(['error' => 'Upload not found or already completed'], 404);
        }

        $chunksDir     = "uploads/{$request->upload_identifier}/chunks";
        $assembledPath = "uploads/{$request->upload_identifier}/assembled/{$upload->filename}";
        Storage::disk($this->disk)->makeDirectory("uploads/{$request->upload_identifier}/assembled");

        // Assemble chunks
        $assembledContent = '';
        for ($i = 0; $i < $request->total_chunks; $i++) {
            $chunkPath = "{$chunksDir}/{$i}.chunk";
            if (!Storage::disk($this->disk)->exists($chunkPath)) {
                return response()->json(['error' => "Missing chunk {$i}"], 400);
            }
            $assembledContent .= Storage::disk($this->disk)->get($chunkPath);
        }

        // Checksum validation
        $calculatedChecksum = hash('sha256', $assembledContent);


//	if ($calculatedChecksum !== $request->checksum) {
 //           return response()->json(['error' => 'Checksum mismatch'], 400);
  //      }

        // ✅ no ACLs
        Storage::disk($this->disk)->put($assembledPath, $assembledContent);

        // Generate variants
        $variantsDir = "uploads/{$request->upload_identifier}/variants";
        Storage::disk($this->disk)->makeDirectory($variantsDir);

        $variants = [
            'original' => null,
            '256px'    => 256,
            '512px'    => 512,
            '1024px'   => 1024,
        ];

        DB::beginTransaction();
        try {
            $upload->images()->delete();

            foreach ($variants as $variant => $width) {
                $variantFilename = "{$variant}_" . $upload->filename;
                $variantPath     = "{$variantsDir}/{$variantFilename}";

                if ($width) {
                    if ($this->disk === 's3') {
                        $image = Image::make(Storage::disk($this->disk)->get($assembledPath))
                            ->resize($width, null, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });

                        Storage::disk($this->disk)->put($variantPath, (string) $image->encode());
                    } else {
                        $image = Image::make(Storage::disk($this->disk)->path($assembledPath))
                            ->resize($width, null, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });

                        $image->save(Storage::disk($this->disk)->path($variantPath));
                    }
                } else {
                    Storage::disk($this->disk)->copy($assembledPath, $variantPath);
                }

                $upload->images()->create([
                    'variant' => $variant,
                    'path'    => $variantPath,
                ]);
            }

            $upload->completed = true;
            $upload->checksum  = $request->checksum;
            $upload->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed processing images: ' . $e->getMessage()], 500);
        }

        // Clean up chunks
        Storage::disk($this->disk)->deleteDirectory($chunksDir);

        return response()->json(['message' => 'Upload completed and images processed']);
    }

    /**
     * Attach upload to product(s)
     */
    public function attachToProduct(Request $request)
    {
        $request->validate([
            'upload_identifier' => 'required|string',
            'product_sku'       => 'required|string',
        ]);

        $upload = Upload::where('upload_identifier', $request->upload_identifier)
            ->lockForUpdate()
            ->first();

        if (!$upload || !$upload->completed) {
            return response()->json(['error' => 'Upload not found or incomplete'], 404);
        }

        $originalImage = $upload->images()->where('variant', 'original')->first();
        if (!$originalImage) {
            return response()->json(['error' => 'Original image not found'], 404);
        }

        $skus = array_map('trim', explode(',', $request->product_sku));

        $attached = [];
        $notFound = [];

        foreach ($skus as $sku) {
            $product = Product::where('sku', $sku)->lockForUpdate()->first();

            if ($product) {
                $product->primary_image_id = $originalImage->id;
                $product->save();
                $attached[] = $sku;
            } else {
                $notFound[] = $sku;
            }
        }

        return response()->json([
            'message'   => 'Attach process completed',
            'attached'  => $attached,
            'not_found' => $notFound,
        ]);
    }
}
