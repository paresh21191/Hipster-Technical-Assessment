<?php

namespace UserDiscounts\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use UserDiscounts\Models\Discount;
use UserDiscounts\Models\UserDiscount;
use UserDiscounts\Models\DiscountAudit;
use UserDiscounts\Events\DiscountAssigned;
use UserDiscounts\Events\DiscountRevoked;
use UserDiscounts\Events\DiscountApplied;
use \App\Models\User;
use Carbon\Carbon;
use Exception;

class DiscountManager
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }
    

    /**
     * Assign a discount to a user.
     *
     * @param User $user
     * @param Discount $discount
     * @return UserDiscount
     */
    public function assign(User $user, Discount $discount): UserDiscount
    {
        $userDiscount = UserDiscount::updateOrCreate(
            [
                'user_id' => $user->id,
                'discount_id' => $discount->id,
            ],
            [
                'assigned_at' => now(),
                'revoked_at' => null,
                'usage_count' => 0,
            ]
        );

        // Audit record
        DiscountAudit::create([
            'user_id' => $user->id,
            'discount_id' => $discount->id,
            'action' => 'assigned',
            'metadata' => null,
            'created_at' => now(),
        ]);

        Event::dispatch(new DiscountAssigned($user, $discount));

        return $userDiscount;
    }

    /**
     * Revoke a discount from a user.
     *
     * @param User $user
     * @param Discount $discount
     * @return bool
     */
    public function revoke(User $user, Discount $discount): bool
    {
        $userDiscount = UserDiscount::where('user_id', $user->id)
            ->where('discount_id', $discount->id)
            ->whereNull('revoked_at')
            ->first();

        if (!$userDiscount) {
            return false;
        }

        $userDiscount->revoked_at = now();
        $userDiscount->save();

        DiscountAudit::create([
            'user_id' => $user->id,
            'discount_id' => $discount->id,
            'action' => 'revoked',
            'metadata' => null,
            'created_at' => now(),
        ]);

        Event::dispatch(new DiscountRevoked($user, $discount));

        return true;
    }

    /**
     * Get all eligible discounts for a user given a context.
     *
     * @param User $user
     * @param mixed $context
     * @return Collection|Discount[]
     */
    public function eligibleFor(User $user, $context = null): Collection
    {
        $now = now();

        return Discount::query()
            ->select('discounts.*')
            ->join('user_discounts', function ($join) use ($user) {
                $join->on('discounts.id', '=', 'user_discounts.discount_id')
                    ->where('user_discounts.user_id', '=', $user->id)
                    ->whereNull('user_discounts.revoked_at');
            })
            ->where('discounts.active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('discounts.expires_at')
                      ->orWhere('discounts.expires_at', '>', $now);
            })
            ->get()
            ->filter(function (Discount $discount) use ($user) {
            
                $userDiscount = UserDiscount::where('user_id', $user->id)
                    ->where('discount_id', $discount->id)
                    ->first();

                if (!$userDiscount) {
                    return false;
                }

                if ($discount->usage_cap !== null &&
                    $userDiscount->usage_count >= $discount->usage_cap) {
                    return false;
                }

                return true;
            })
            ->sortBy(function (Discount $discount) {
                // Sort by stacking_order config priority
                $order = $this->config['stacking_order'];
                return array_search($discount->type, $order) ?: 999;
            })->values();
    }

    /**
     * Apply eligible discounts to an amount.
     *
     * @param User $user
     * @param float $amount
     * @param mixed $context
     * @return float
     * @throws Exception
     */
    public function apply(User $user, float $amount, $context = null): float
    {
        $eligibleDiscounts = $this->eligibleFor($user, $context);

        $originalAmount = $amount;
        $totalPercentage = 0.0;
        $totalFixed = 0.0;

        DB::beginTransaction();

        try {
            foreach ($eligibleDiscounts as $discount) {
                // Lock user_discount row to prevent concurrency issues
                $userDiscount = UserDiscount::where('user_id', $user->id)
                    ->where('discount_id', $discount->id)
                    ->lockForUpdate()
                    ->first();

                if (!$userDiscount || $userDiscount->revoked_at !== null) {
                    continue;
                }

            
                if ($discount->usage_cap !== null &&
                    $userDiscount->usage_count >= $discount->usage_cap) {
                    continue;
                }

                if ($discount->type === 'percentage') {
                    $totalPercentage += $discount->value;
                    if ($totalPercentage > $this->config['max_percentage_cap']) {
                        $totalPercentage = $this->config['max_percentage_cap'];
                        // We do not apply further percentage discounts beyond cap
                        break;
                    }
                } elseif ($discount->type === 'fixed') {
                    $totalFixed += $discount->value;
                }
            }

            
            $amountAfterPercentage = $amount * (1 - $totalPercentage / 100);

            // Apply fixed discounts after percentage
            $finalAmount = $amountAfterPercentage - $totalFixed;
            if ($finalAmount < 0) {
                $finalAmount = 0;
            }

            // Apply rounding
            switch ($this->config['rounding']) {
                case 'up':
                    $finalAmount = ceil($finalAmount * 100) / 100;
                    break;
                case 'down':
                    $finalAmount = floor($finalAmount * 100) / 100;
                    break;
                case 'nearest':
                default:
                    $finalAmount = round($finalAmount, 2);
                    break;
            }

            // Increment usage_count and save audit for each applied discount
            foreach ($eligibleDiscounts as $discount) {
                $userDiscount = UserDiscount::where('user_id', $user->id)
                    ->where('discount_id', $discount->id)
                    ->lockForUpdate()
                    ->first();

                if (!$userDiscount || $userDiscount->revoked_at !== null) {
                    continue;
                }

                // Check usage cap again after locking
                if ($discount->usage_cap !== null &&
                    $userDiscount->usage_count >= $discount->usage_cap) {
                    continue;
                }

                // Determine if discount was actually applied
                $applied = false;
                if ($discount->type === 'percentage' && $totalPercentage > 0) {
                    $applied = true;
                } elseif ($discount->type === 'fixed' && $totalFixed > 0) {
                    $applied = true;
                }

                if (!$applied) {
                    continue;
                }

                // Increment usage_count
                $userDiscount->usage_count++;
                $userDiscount->save();

                // Create audit record
                DiscountAudit::create([
                    'user_id' => $user->id,
                    'discount_id' => $discount->id,
                    'action' => 'applied',
                    'metadata' => [
                        'amount_before' => $originalAmount,
                        'amount_after' => $finalAmount,
                    ],
                    'created_at' => now(),
                ]);

                Event::dispatch(new DiscountApplied($user, $discount, $originalAmount, $finalAmount));
            }

            DB::commit();

            return $finalAmount;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}