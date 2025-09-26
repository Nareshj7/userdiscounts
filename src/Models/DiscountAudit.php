<?php

namespace Naresh\UserDiscounts\Models;

use Naresh\UserDiscounts\Traits\ResolvesUserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountAudit extends Model
{
    use ResolvesUserModel;

    protected $fillable = [
        'user_id',
        'discount_id',
        'action',
        'order_id',
        'original_amount',
        'final_amount',
        'discount_amount',
        'context',
    ];

    protected $casts = [
        'original_amount' => 'float',
        'final_amount' => 'float',
        'discount_amount' => 'float',
        'context' => 'array',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->resolveUserModel(), 'user_id');
    }
}