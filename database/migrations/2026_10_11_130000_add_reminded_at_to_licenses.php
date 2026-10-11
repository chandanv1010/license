<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh dau da nhac gia han chua.
 *
 * Goi nam ma khong nhac thi gan nhu 100% khach roi o thang thu 12 - sau mot
 * nam ho khong con nho minh da mua gi. Cot nay de khong nhac hai lan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
