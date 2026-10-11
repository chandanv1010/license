<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Don hang ---------------------------------------------------------
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Ma di vao noi dung chuyen khoan. Day la thu duy nhat noi mot lan
            // chuyen tien voi mot don hang, nen phai duy nhat.
            $table->string('code', 16)->unique();
            $table->string('product', 32);
            $table->string('plan', 32);
            // Tien tinh bang DONG, so nguyen. Xem chu thich o config/license.php.
            $table->unsignedBigInteger('amount');
            $table->string('email')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // --- Giao dich nhan duoc tu SePay --------------------------------------
        //
        // Ghi lai MOI giao dich bao ve, ke ca cai khong khop don nao. Chuyen
        // khoan sai noi dung la chuyen xay ra thuong xuyen, va khi khach keu
        // "toi chuyen roi ma khong nhan duoc key" thi day la cho duy nhat tra
        // duoc.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Id cua SePay. Duy nhat de webhook goi lai lan hai khong cap them
            // mot license nua - SePay co the gui lai khi lan dau timeout.
            $table->string('sepay_id', 64)->unique();
            $table->string('gateway', 64)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->unsignedBigInteger('amount');
            $table->text('content')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // Vi sao khong khop duoc don nao, hoac khop roi thi ghi chu gi.
            $table->string('note')->nullable();
            // Giu nguyen goi tin de con doi chieu khi SePay doi dinh dang.
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        // --- License ----------------------------------------------------------
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64)->unique();
            $table->string('product', 32);
            $table->string('email')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('max_devices')->default(3);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['product', 'expires_at']);
        });

        // --- May da kich hoat ---------------------------------------------------
        //
        // Khong gioi han so may thi mot key dan len dien dan la ca the gioi dung
        // chung, va san pham thanh mien phi trong mot dem.
        Schema::create('license_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('device_hash', 64);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'device_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_devices');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
    }
};
