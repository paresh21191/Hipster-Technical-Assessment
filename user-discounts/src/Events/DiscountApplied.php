<?php

namespace UserDiscounts\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use UserDiscounts\Models\Discount;
use \App\Models\User;

class DiscountApplied
{
    use Dispatchable, SerializesModels;

    public User $user;
    public Discount $discount;
    public float $amountBefore;
    public float $amountAfter;

    public function __construct(User $user, Discount $discount, float $amountBefore, float $amountAfter)
    {
        $this->user = $user;
        $this->discount = $discount;
        $this->amountBefore = $amountBefore;
        $this->amountAfter = $amountAfter;
    }
}
