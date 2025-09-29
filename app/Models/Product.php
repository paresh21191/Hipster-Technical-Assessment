<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['sku', 'name', 'description', 'price', 'primary_image_id'];

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }
}