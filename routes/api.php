<?php

use App\Http\Controllers\LicenseController;
use App\Http\Controllers\SePayWebhookController;
use Illuminate\Support\Facades\Route;

// SePay goi toi khi co tien ve. Xac thuc bang chu ky HMAC hoac token o header,
// khong phai bang phien dang nhap - nen khong co csrf va khong co session.
Route::post('sepay/webhook', SePayWebhookController::class)->name('sepay.webhook');

// Extension goi toi de hoi key con hieu luc khong.
Route::post('license/verify', [LicenseController::class, 'verify'])->name('license.verify');

// Hai duong dan cua trang mua (/api/order...) nam o routes/web.php - chung
// can phien lam viec de khong sinh don trung. Xem chu thich ben do.
