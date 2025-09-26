<?php

namespace Codex\UserDiscounts\Services;

use Codex\UserDiscounts\Contracts\DiscountManager as DiscountManagerContract;
use Codex\UserDiscounts\Events\DiscountApplied;
use Codex\UserDiscounts\Events\DiscountAssigned;
use Codex\UserDiscounts\Events\DiscountRevoked;
use Codex\UserDiscounts\Exceptions\DiscountException;
use Codex\UserDiscounts\Exceptions\DiscountNotEligibleException;
use Codex\UserDiscounts\Exceptions\DiscountNotFoundException;
use Codex\UserDiscounts\Exceptions\DiscountUsageExceededException;
use Codex\UserDiscounts\Models\Discount;
use Codex\UserDiscounts\Models\DiscountAudit;
use Codex\UserDiscounts\Models\UserDiscount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class DiscountManager implements DiscountManagerContract
{
    public function assign(Authenticatable $user, string $code, array $attributes = []): void
    {
        $discount = $this->findDiscountByCode($code);

        if (!$this->isCurrentlyActive($discount)) {
            throw new DiscountNotEligibleException("Discount {$code} is not active.");
        }

        $userId = $user->getAuthIdentifier();
        $now = now();

        DB::transaction(function () use ($userId, $discount, $attributes, $now, $user): void {
            $userDiscount = UserDiscount::query()
                ->forUser($userId)
                ->where('discount_id', $discount->getKey())
                ->lockForUpdate()
                ->first();

            if ($userDiscount && is_null($userDiscount->revoked_at)) {
                return; // idempotent assignment
            }

            if (!$userDiscount) {
                $userDiscount = new UserDiscount([
                    'user_id' => $userId,
                    'discount_id' => $discount->getKey(),
                ]);
            }

            $userDiscount->assigned_at = Arr::get($attributes, 'assigned_at', $now);
            $userDiscount->revoked_at = null;
            $userDiscount->save();

            $this->recordAudit($userId, $discount, 'assigned', [
                'context' => Arr::get($attributes, 'context'),
            ]);

            Event::dispatch(new DiscountAssigned($user, $discount, $userDiscount));
        });
    }

    public function revoke(Authenticatable $user, string $code): void
    {
        $discount = $this->findDiscountByCode($code);
        $userId = $user->getAuthIdentifier();
        $now = now();

        DB::transaction(function () use ($userId, $discount, $now, $user): void {
            $userDiscount = UserDiscount::query()
                ->forUser($userId)
                ->where('discount_id', $discount->getKey())
                ->lockForUpdate()
                ->first();

            if (!$userDiscount) {
                throw new DiscountNotFoundException('Discount not assigned to user.');
            }

            if ($userDiscount->isRevoked()) {
                return;
            }

            $userDiscount->revoked_at = $now;
            $userDiscount->save();

            $this->recordAudit($userId, $discount, 'revoked');

            Event::dispatch(new DiscountRevoked($user, $discount, $userDiscount));
        });
    }

    public function eligibleFor(Authenticatable $user, array $context = []): Collection
    {
        $userId = $user->getAuthIdentifier();
        $amount = (float) Arr::get($context, 'amount', 0);

        return UserDiscount::query()
            ->forUser($userId)
            ->active()
            ->with('discount')
            ->get()
            ->filter(fn (UserDiscount $userDiscount): bool =>
                $userDiscount->discount &&
                $this->isCurrentlyActive($userDiscount->discount) &&
                $this->meetsOrderValue($userDiscount->discount, $amount) &&
                $userDiscount->hasUsageRemaining()
            )
            ->map(function (UserDiscount $userDiscount) {
                $discount = $userDiscount->discount;
                $discount->setRelation('userDiscount', $userDiscount);

                return $discount;
            })
            ->values();
    }

    public function apply(Authenticatable $user, float $amount, array $context = []): float
    {
        $userId = $user->getAuthIdentifier();
        $originalAmount = $this->roundAmount($amount);
        $precision = (int) config('user-discounts.caps.precision', 2);

        return DB::transaction(function () use ($userId, $user, $originalAmount, $context, $precision) {
            $userDiscounts = UserDiscount::query()
                ->forUser($userId)
                ->active()
                ->with('discount')
                ->lockForUpdate()
                ->get();

            $eligible = $userDiscounts->filter(function (UserDiscount $userDiscount) use ($originalAmount) {
                $discount = $userDiscount->discount;

                return $discount &&
                    $this->isCurrentlyActive($discount) &&
                    $this->meetsOrderValue($discount, $originalAmount) &&
                    $userDiscount->hasUsageRemaining();
            });

            $ordered = $this->orderDiscounts($eligible);

            $runningAmount = $originalAmount;
            $totalDiscountApplied = 0.0;
            $maxPercentage = config('user-discounts.caps.max_percentage');

            foreach ($ordered as $userDiscount) {
                $discount = $userDiscount->discount;

                if (!$discount) {
                    continue;
                }

                $discountAmount = $this->calculateDiscountAmount(
                    $discount,
                    $runningAmount,
                    $originalAmount,
                    $totalDiscountApplied,
                    $maxPercentage
                );

                if ($discountAmount <= 0.0) {
                    continue;
                }

                if ($discount->max_discount_amount) {
                    $discountAmount = min($discountAmount, (float) $discount->max_discount_amount);
                }

                $discountAmount = min($discountAmount, $runningAmount);
                $discountAmount = $this->roundAmount($discountAmount, $precision);

                if ($discountAmount <= 0.0) {
                    continue;
                }

                if ($discount->max_uses_per_user && $userDiscount->usage_count >= $discount->max_uses_per_user) {
                    throw new DiscountUsageExceededException('Usage limit reached.');
                }

                $runningAmount = $this->roundAmount($runningAmount - $discountAmount, $precision);
                $totalDiscountApplied += $discountAmount;

                $userDiscount->usage_count += 1;
                $userDiscount->last_used_at = now();
                $userDiscount->save();

                $this->recordAudit($userId, $discount, 'applied', [
                    'order_id' => Arr::get($context, 'order_id'),
                    'original_amount' => $originalAmount,
                    'final_amount' => $runningAmount,
                    'discount_amount' => $discountAmount,
                    'context' => $context,
                ]);

                Event::dispatch(new DiscountApplied(
                    $user,
                    $discount,
                    $userDiscount,
                    $originalAmount,
                    $runningAmount,
                    $discountAmount
                ));

                if (!$discount->is_stackable) {
                    break;
                }

                if ($maxPercentage !== null && $maxPercentage > 0) {
                    $capAmount = $originalAmount * ($maxPercentage / 100);

                    if ($totalDiscountApplied >= $capAmount) {
                        break;
                    }
                }
            }

            return $this->roundAmount($runningAmount, $precision);
        });
    }

    private function findDiscountByCode(string $code): Discount
    {
        $discount = Discount::query()->where('code', Str::upper($code))->first();

        if (!$discount) {
            $discount = Discount::query()->where('code', $code)->first();
        }

        if (!$discount) {
            throw new DiscountNotFoundException("Discount {$code} not found.");
        }

        return $discount;
    }

    private function isCurrentlyActive(Discount $discount): bool
    {
        if (!$discount->is_active) {
            return false;
        }

        $now = now();

        if ($discount->starts_at && $discount->starts_at->isFuture()) {
            return false;
        }

        if ($discount->expires_at && $discount->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    private function meetsOrderValue(Discount $discount, float $amount): bool
    {
        if (!$discount->min_order_value) {
            return true;
        }

        return $amount >= (float) $discount->min_order_value;
    }

    private function orderDiscounts(Collection $userDiscounts): Collection
    {
        $order = config('user-discounts.stacking.order', 'priority');
        $direction = config('user-discounts.stacking.direction', 'desc');

        return $userDiscounts->sort(function (UserDiscount $a, UserDiscount $b) use ($order, $direction) {
            $first = $this->resolveSortValue($a, $order);
            $second = $this->resolveSortValue($b, $order);

            if ($first === $second) {
                return strcmp($a->discount->code, $b->discount->code);
            }

            if ($direction === 'desc') {
                return $second <=> $first;
            }

            return $first <=> $second;
        })->values();
    }

    private function resolveSortValue(UserDiscount $userDiscount, string $order): mixed
    {
        return match ($order) {
            'assigned_at' => optional($userDiscount->assigned_at)->getTimestamp() ?? 0,
            'usage_count' => $userDiscount->usage_count,
            default => $userDiscount->discount?->priority ?? 0,
        };
    }

    private function calculateDiscountAmount(
        Discount $discount,
        float $currentAmount,
        float $originalAmount,
        float $totalDiscountApplied,
        ?float $maxPercentage
    ): float {
        if ($discount->type === 'percentage') {
            $calculated = $currentAmount * ((float) $discount->value / 100);

            if ($maxPercentage !== null && $maxPercentage > 0) {
                $capAmount = $originalAmount * ($maxPercentage / 100);
                $remainingCap = max(0.0, $capAmount - $totalDiscountApplied);
                $calculated = min($calculated, $remainingCap);
            }

            if ($discount->max_discount_amount) {
                $calculated = min($calculated, (float) $discount->max_discount_amount);
            }

            return $calculated;
        }

        if ($discount->type === 'fixed') {
            return min((float) $discount->value, $currentAmount);
        }

        throw new DiscountException('Unsupported discount type.');
    }

    private function recordAudit(int|string $userId, Discount $discount, string $action, array $attributes = []): void
    {
        DiscountAudit::query()->create([
            'user_id' => $userId,
            'discount_id' => $discount->getKey(),
            'action' => $action,
            'order_id' => Arr::get($attributes, 'order_id'),
            'original_amount' => Arr::get($attributes, 'original_amount'),
            'final_amount' => Arr::get($attributes, 'final_amount'),
            'discount_amount' => Arr::get($attributes, 'discount_amount'),
            'context' => Arr::get($attributes, 'context'),
        ]);
    }

    private function roundAmount(float $amount, int $precision = null): float
    {
        $precision = $precision ?? (int) config('user-discounts.caps.precision', 2);
        $mode = config('user-discounts.caps.rounding', 'round');

        return match ($mode) {
            'floor' => floor($amount * (10 ** $precision)) / (10 ** $precision),
            'ceil' => ceil($amount * (10 ** $precision)) / (10 ** $precision),
            default => round($amount, $precision, PHP_ROUND_HALF_UP),
        };
    }
}