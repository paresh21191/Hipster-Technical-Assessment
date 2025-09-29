<?php

namespace UserDiscounts\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use UserDiscounts\Models\Discount;
use \App\Models\User;

class DiscountAssigned
{
    use Dispatchable, SerializesModels;

    public User $user;
    public Discount $discount;

    public function __construct(User $user, Discount $discount)
    {
        $this->user = $user;
        $this->discount = $discount;
    }
}