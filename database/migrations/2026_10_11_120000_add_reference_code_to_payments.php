<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Them ma tham chieu cua ngan hang vao bang payments.
 *
 * VI SAO CAN: webhook va API cua SePay dung HAI dinh danh khac nhau cho CUNG
 * MOT giao dich -
 *
 *   webhook : "id": 92704                                 (so)
 *   API v2  : "id": "a520ea96-c528-11f1-b21a-a6006ab65aca" (UUID)
 *
 * Chi chong trung theo `sepay_id` thi mot giao dich da xu ly qua webhook se bi
 * lenh doi soat ghi lai lan nua. Ma tham chieu cua ngan hang
 * (`referenceCode` / `reference_number`) la thu GIONG NHAU o ca hai duong, nen
 * no la khoa chong trung that su.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference_code', 64)->nullable()->unique()->after('sepay_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['reference_code']);
            $table->dropColumn('reference_code');
        });
    }
};
