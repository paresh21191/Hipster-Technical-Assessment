<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Upload extends Model
{
    protected $fillable = ['upload_identifier', 'filename', 'checksum', 'completed', 'product_id'];

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}