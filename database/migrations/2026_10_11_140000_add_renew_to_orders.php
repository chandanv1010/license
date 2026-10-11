<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Don gia han tro vao license cu.
 *
 * Truoc day mua tiep la sinh license MOI: nguoi dung phai go lai ma o moi may,
 * ma cu thanh rac, va neu ho quen doi thi den ngay het han la extension tat.
 * Gia han dung phai la CONG THEM ngay vao chinh cai ma ho dang dung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('renew_license_id')
                ->nullable()
                ->after('plan')
                // License bi xoa thi don van con (don la chung tu), chi mat
                // moi lien he gia han.
                ->constrained('licenses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renew_license_id');
        });
    }
};
