<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const CHUA_TRA = 'pending';
    public const DA_TRA = 'paid';

    protected $fillable = [
        'code', 'product', 'plan', 'renew_license_id', 'amount', 'email', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function license(): HasOne
    {
        return $this->hasOne(License::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** License ma don nay gia han cho, neu la don gia han. */
    public function licenseGiaHan(): BelongsTo
    {
        return $this->belongsTo(License::class, 'renew_license_id');
    }

    public function laGiaHan(): bool
    {
        return $this->renew_license_id !== null;
    }

    /**
     * License cua don nay, du la don moi hay don gia han.
     *
     * Don gia han KHONG co quan he `license()`: cot `licenses.order_id` van
     * tro ve don mua lan dau. Thieu cho nay thi trang mua ngoi doi mot ma
     * khong bao gio hien, va webhook goi lai lan hai se cong them ngay nua.
     */
    public function licenseKetQua(): ?License
    {
        return $this->licenseGiaHan ?: $this->license;
    }

    public function daTra(): bool
    {
        return $this->status === self::DA_TRA;
    }
}
