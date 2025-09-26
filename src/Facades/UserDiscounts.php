<?php

namespace Naresh\UserDiscounts\Facades;

use Naresh\UserDiscounts\Contracts\DiscountManager as DiscountManagerContract;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void assign(\Illuminate\Contracts\Auth\Authenticatable $user, string $code, array $attributes = [])
 * @method static void revoke(\Illuminate\Contracts\Auth\Authenticatable $user, string $code)
 * @method static \Illuminate\Support\Collection eligibleFor(\Illuminate\Contracts\Auth\Authenticatable $user, array $context = [])
 * @method static float apply(\Illuminate\Contracts\Auth\Authenticatable $user, float $amount, array $context = [])
 */
class UserDiscounts extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DiscountManagerContract::class;
    }
}