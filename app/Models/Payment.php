<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'sepay_id', 'reference_code', 'gateway', 'account_number', 'amount',
        'content', 'transferred_at', 'order_id', 'note', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transferred_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
