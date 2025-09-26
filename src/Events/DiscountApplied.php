<?php

namespace Codex\UserDiscounts\Events;

use Codex\UserDiscounts\Models\Discount;
use Codex\UserDiscounts\Models\UserDiscount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountApplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Authenticatable $user,
        public Discount $discount,
        public UserDiscount $userDiscount,
        public float $originalAmount,
        public float $finalAmount,
        public float $discountAmount
    ) {
    }
}