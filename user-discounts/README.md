# User Discounts Laravel Package

## Introduction

A reusable, testable discount system for Laravel users with support for stacking, usage caps, events, and concurrency safety.

## Installation
add in main composer.json
"repositories": [
    {
        "type": "path",
        "url": "user-discounts"
    }
]



```bash

composer require user-discounts/user-discounts:@dev

php artisan vendor:publish --tag="user-discounts-migrations"


# steps to run this package


step 1

php artisan migrate --env=testing

step 2

php artisan tinker --env=testing

step 3

in  tinker 

use App\Models\User;
use UserDiscounts\Models\Discount;
use UserDiscounts\Services\DiscountManager;

// to create a test data

$user = User::first() ?? User::factory()->create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('password')
]);

$discount = Discount::first() ?? Discount::create([
    'name' => '10% Off',
    'type' => 'percentage',
    'value' => 10,
    'active' => true
]);


// get configs

$config = config('user-discounts'); // takes from config/user-discounts.php
$manager = new DiscountManager($config);

// assign discounts

$userDiscount = $manager->assign($user, $discount);
$userDiscount 

// Apply it

$amount = 100;
$finalAmount = $manager->apply($user, $amount);
$finalAmount 

// test revoking discount
$manager->revoke($user, $discount);

// verify eligible discounts
$eligible = $manager->eligibleFor($user);
$eligible // shows all active and non-revoked discounts

