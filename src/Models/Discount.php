<?php

namespace Codex\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Discount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_order_value',
        'max_discount_amount',
        'starts_at',
        'expires_at',
        'is_active',
        'max_uses_per_user',
        'priority',
        'is_stackable',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'value' => 'float',
        'min_order_value' => 'float',
        'max_discount_amount' => 'float',
        'max_uses_per_user' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEligible(Builder $query, float $amount): Builder
    {
        $now = Carbon::now();

        return $query
            ->where(function (Builder $inner) use ($now) {
                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $inner) use ($now) {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->where(function (Builder $inner) use ($amount) {
                $inner->whereNull('min_order_value')->orWhere('min_order_value', '<=', $amount);
            });
    }

    public function userDiscounts(): HasMany
    {
        return $this->hasMany(UserDiscount::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(DiscountAudit::class);
    }

    public function hasUsageCap(): bool
    {
        return !is_null($this->max_uses_per_user);
    }

    public function isPercentage(): bool
    {
        return $this->type === 'percentage';
    }

    public function isFixed(): bool
    {
        return $this->type === 'fixed';
    }
}