<?php

namespace Naresh\UserDiscounts\Models;

use Naresh\UserDiscounts\Traits\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDiscount extends Model
{
    use ResolvesUserModel;

    protected $fillable = [
        'user_id',
        'discount_id',
        'assigned_at',
        'revoked_at',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->resolveUserModel(), 'user_id');
    }

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    public function hasUsageRemaining(): bool
    {
        $cap = $this->discount?->max_uses_per_user;

        if (is_null($cap)) {
            return true;
        }

        return $this->usage_count < $cap;
    }
}