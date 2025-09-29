<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UploadController;

Route::get('/', [ProductController::class, 'index'])->name('products.index');

// CSV Import
Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');

// Image Upload
Route::post('/uploads/init', [UploadController::class, 'init'])->name('uploads.init'); // initialize upload
Route::post('/uploads/chunk', [UploadController::class, 'uploadChunk'])->name('uploads.chunk'); // upload chunk
Route::post('/uploads/complete', [UploadController::class, 'complete'])->name('uploads.complete'); // complete upload and process variants
Route::post('/uploads/attach', [UploadController::class, 'attachToProduct'])->name('uploads.attach'); // attach image to product