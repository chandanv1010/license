<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    protected $fillable = [
        'order_id', 'key', 'product', 'email',
        'starts_at', 'expires_at', 'max_devices', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_devices' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(LicenseDevice::class);
    }

    public function biThuHoi(): bool
    {
        return $this->revoked_at !== null;
    }

    public function conHan(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * So ngay con lai, lam tron len.
     *
     * Lam tron LEN co chu dich: con 0,4 ngay thi nguoi dung van dung duoc hom
     * nay, bao "con 0 ngay" la sai su that.
     */
    public function soNgayConLai(): int
    {
        return max(0, (int) ceil(now()->diffInDays($this->expires_at, false)));
    }
}
