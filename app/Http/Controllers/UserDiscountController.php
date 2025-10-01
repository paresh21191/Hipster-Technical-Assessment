<?php

namespace App\Http\Controllers;

use App\Models\User;
use UserDiscounts\Services\DiscountManager;
use UserDiscounts\Models\Discount;
use UserDiscounts\Models\DiscountAudit;

class UserDiscountController extends Controller
{
    public function seed()
    {
        $user = User::firstOrCreate(
            ['email' => 'demouser@test.com'],
            ['name' => 'Demo User', 'password' => bcrypt('password')]
        );

        $discount = Discount::firstOrCreate(
            ['name' => 'Demo user Discount'],
            ['type' => 'fixed', 'value' => 10, 'active' => true, 'usage_cap' => 2]
        );

        return response()->json([
            'user' => $user,
            'discount' => $discount,
            'message' => 'Seeded demo user and discount'
        ]);
    }

        public function showDemo()
    {
        $users = \App\Models\User::all();
        return view('demo-discount', compact('users'));
    }

    public function applyDemo(\Illuminate\Http\Request $request, \UserDiscounts\Services\DiscountManager $manager)
    {
        $user = \App\Models\User::findOrFail($request->user_id);
        $final = $manager->apply($user, $request->amount);

        return back()->with([
            'original' => $request->amount,
            'final' => $final,
            'user' => $user->name,
        ]);
    }

    public function assign($userId, $discountId, DiscountManager $manager)
    {
        $user = User::findOrFail($userId);
        $discount = Discount::findOrFail($discountId);

        $userDiscount = $manager->assign($user, $discount);

        return response()->json([
            'message' => 'Discount assigned',
            'user_discount' => $userDiscount
        ]);
    }

    public function apply($userId, $amount, DiscountManager $manager)
    {
        $user = User::findOrFail($userId);
        $finalAmount = $manager->apply($user, $amount);

        return response()->json([
            'original_amount' => $amount,
            'final_amount' => $finalAmount,
            'applied_discounts' => $manager->eligibleFor($user),
            'audits' => DiscountAudit::where('user_id', $user->id)->get(),
        ]);
    }

    public function revoke($userId, $discountId, DiscountManager $manager)
    {
        $user = User::findOrFail($userId);
        $discount = Discount::findOrFail($discountId);

        $revoked = $manager->revoke($user, $discount);

        return response()->json([
            'message' => $revoked ? 'Discount revoked' : 'Discount not found or already revoked',
        ]);
    }
}
