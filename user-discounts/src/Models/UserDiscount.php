<?php

namespace UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDiscount extends Model
{
    protected $table = 'user_discounts';

    protected $fillable = [
        'user_id',
        'discount_id',
        'usage_count',
        'assigned_at',
        'revoked_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function user(): BelongsTo
    {
        
        return $this->belongsTo(\App\Models\User::class);
    }
}