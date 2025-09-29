<?php

use App\Models\User;
use UserDiscounts\Models\Discount;
use UserDiscounts\Services\DiscountManager;

// --- 1️⃣ Load test data or create if missing ---

$user = User::first() ?? User::factory()->create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('password')
]);

$discount1 = Discount::first() ?? Discount::create([
    'name' => '10% Off',
    'type' => 'percentage',
    'value' => 10,
    'active' => true
]);

$discount2 = Discount::where('name', 'Fixed $20 Off')->first() ?? Discount::create([
    'name' => 'Fixed $20 Off',
    'type' => 'fixed',
    'value' => 20,
    'active' => true
]);

// --- 2️⃣ Initialize DiscountManager with package config ---

$config = config('user-discounts'); // Loads config/user-discounts.php
$manager = new DiscountManager($config);

// --- 3️⃣ Assign discounts to user ---

$userDiscount1 = $manager->assign($user, $discount1);
$userDiscount2 = $manager->assign($user, $discount2);

echo "✅ Assigned discounts to user: \n";
echo "- Discount 1: {$userDiscount1->id}\n";
echo "- Discount 2: {$userDiscount2->id}\n\n";

// --- 4️⃣ Apply discounts on an amount ---

$amount = 100;
$finalAmount = $manager->apply($user, $amount);

echo "💰 Original Amount: $amount\n";
echo "💵 After Applying Discounts: $finalAmount\n\n";

// --- 5️⃣ Revoke a discount ---

$manager->revoke($user, $discount1);
echo "❌ Revoked discount: {$discount1->name}\n\n";

// --- 6️⃣ Verify remaining discounts ---

$remaining = $manager->eligibleFor($user);
echo "📋 Remaining eligible discounts for user:\n";
foreach ($remaining as $d) {
    echo "- {$d->name} ({$d->type}, value: {$d->value})\n";
}
