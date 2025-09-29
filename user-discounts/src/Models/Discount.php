<?php

namespace UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    protected $table = 'discounts';

    protected $fillable = [
        'name',
        'type',
        'value',
        'active',
        'expires_at',
        'usage_cap',
    ];

    protected $casts = [
        'active' => 'boolean',
        'expires_at' => 'datetime',
        'value' => 'float',
    ];

    public function userDiscounts(): HasMany
    {
        return $this->hasMany(UserDiscount::class);
    }

    public function discountAudits(): HasMany
    {
        return $this->hasMany(DiscountAudit::class);
    }
}