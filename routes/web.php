<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UploadController;
use YourVendor\DiscountPackage\Facades\Discount;
use App\Models\User;
use App\Http\Controllers\UserDiscountController;

Route::get('/', [ProductController::class, 'index'])->name('products.index');



// CSV Import
Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');

// Image Upload
Route::post('/uploads/init', [UploadController::class, 'init'])->name('uploads.init'); // initialize upload
Route::post('/uploads/chunk', [UploadController::class, 'uploadChunk'])->name('uploads.chunk'); // upload chunk
Route::post('/uploads/complete', [UploadController::class, 'complete'])->name('uploads.complete'); // complete upload and process variants
Route::post('/uploads/attach', [UploadController::class, 'attachToProduct'])->name('uploads.attach'); // attach image to product

Route::prefix('demo')->group(function () {
    Route::get('seed', [UserDiscountController::class, 'seed']);
    Route::post('apply', [UserDiscountController::class, 'applyDemo'])->name('demo.apply');
    Route::get('assign/{user}/{discount}', [UserDiscountController::class, 'assign']);
    Route::get('apply/{user}/{amount}', [UserDiscountController::class, 'apply']);
    Route::get('revoke/{user}/{discount}', [UserDiscountController::class, 'revoke']);
});



Route::get('/cv', function () {
    $path = public_path('cv/PareshL7_.pdf');

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path);
})->name('cv');
