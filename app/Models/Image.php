<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $fillable = ['upload_id', 'variant', 'path'];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}