<?php

namespace Codex\UserDiscounts\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface DiscountManager
{
    public function assign(Authenticatable $user, string $code, array $attributes = []): void;

    public function revoke(Authenticatable $user, string $code): void;

    public function eligibleFor(Authenticatable $user, array $context = []): Collection;

    public function apply(Authenticatable $user, float $amount, array $context = []): float;
}