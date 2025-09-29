<?php

use UserDiscounts\Services\DiscountManager;
use UserDiscounts\Models\Discount;
use App\Models\User;

require __DIR__.'/../vendor/autoload.php';



// Fetch user and discount instances (example)
$user = User::find(1);
$discount = Discount::find(1);

$manager = app(DiscountManager::class);

// Assign discount
$manager->assign($user, $discount);
echo "Discount assigned.\n";

// Check eligible discounts
$eligible = $manager->eligibleFor($user);
echo "Eligible discounts:\n";
foreach ($eligible as $d) {
    echo "- {$d->name} ({$d->type}: {$d->value})\n";
}

// Apply discounts to an amount
$amount = 100.00;
$finalAmount = $manager->apply($user, $amount);
echo "Original amount: $amount\n";
echo "Amount after discounts: $finalAmount\n";

// Revoke discount
$manager->revoke($user, $discount);
echo "Discount revoked.\n";