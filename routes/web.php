<?php

use App\Http\Controllers\Admin\DangNhapController;
use App\Http\Controllers\BienNhanController;
use App\Http\Controllers\Admin\QuanTriController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TraCuuController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('mua', 'nopause'));
Route::get('/mua/{sanPham}', [OrderController::class, 'trangMua'])->name('mua');

/*
|--------------------------------------------------------------------------
| Hai duong dan cua trang mua
|--------------------------------------------------------------------------
|
| Dat o day chu khong o routes/api.php vi can PHIEN LAM VIEC: mot nguoi bam
| "Lay ma QR" nhieu lan phai nhan lai DUNG don cu chu khong sinh don moi.
| Khong co phien thi moi lan tai trang la them mot dong rac trong CSDL, va
| nguoi mua co the dang cam mot ma QR da cu.
|
| Chan dong theo IP: tao don 10 lan/phut, hoi trang thai 120 lan/phut. Trang
| hoi 3 giay mot lan nen MOT tab dung 20 lan/phut - 120 du cho vai tab va cho
| nhieu nguoi chung mot IP van phong.
|
*/
Route::post('/api/order', [OrderController::class, 'tao'])
    ->middleware('throttle:10,1')
    ->name('order.create');

Route::get('/api/order/{ma}/status', [OrderController::class, 'trangThai'])
    ->middleware('throttle:120,1')
    ->name('order.status');

/*
|--------------------------------------------------------------------------
| Quan tri
|--------------------------------------------------------------------------
|
| Mot tai khoan duy nhat, tao bang `php artisan license:admin`. Khong co dang
| ky, khong co quen mat khau - moi duong dan them vao day la mot cho nua phai
| nho ma khoa, trong khi chi co mot nguoi dung.
|
*/
Route::prefix('quan-tri')->name('admin.')->group(function () {
    Route::get('dang-nhap', [DangNhapController::class, 'form'])->name('dang-nhap');
    Route::post('dang-nhap', [DangNhapController::class, 'vao'])->middleware('throttle:20,1');

    Route::middleware('auth')->group(function () {
        Route::post('dang-xuat', [DangNhapController::class, 'ra'])->name('dang-xuat');

        Route::get('/', [QuanTriController::class, 'tongQuan'])->name('tong-quan');
        Route::get('don-hang', [QuanTriController::class, 'donHang'])->name('don-hang');
        Route::get('giao-dich', [QuanTriController::class, 'giaoDich'])->name('giao-dich');
        Route::get('license', [QuanTriController::class, 'license'])->name('license');

        // Nhung viec sua du lieu - deu la POST, khong bao gio GET: mot duong
        // dan GET thu hoi license la chi can lua nguoi dang nhap bam vao anh
        // la license bay mat.
        Route::post('giao-dich/{giaoDich}/gan', [QuanTriController::class, 'ganGiaoDich'])->name('gan-giao-dich');
        Route::post('license/{license}/thu-hoi', [QuanTriController::class, 'thuHoi'])->name('thu-hoi');
        Route::post('license/{license}/bo-thu-hoi', [QuanTriController::class, 'boThuHoi'])->name('bo-thu-hoi');
        Route::post('license/{license}/may/{may}/go', [QuanTriController::class, 'goMay'])->name('go-may');
        Route::post('license/{license}/go-het-may', [QuanTriController::class, 'goHetMay'])->name('go-het-may');
        Route::post('license/{license}/cong-ngay', [QuanTriController::class, 'congNgay'])->name('cong-ngay');
        Route::post('license/{license}/gui-lai', [QuanTriController::class, 'guiLaiMa'])->name('gui-lai-ma');
    });
});

// Bien nhan cho mot don da tra. Duong dan duoc KY: ma don chi 8 ky tu, khong
// ky thi do ma la xem duoc bien nhan - va email - cua nguoi khac.
Route::get('/bien-nhan/{ma}', [BienNhanController::class, 'xem'])
    ->middleware('signed')
    ->name('bien-nhan');

// Chinh sach rieng tu. BAT BUOC phai co khi xin quyen <all_urls> tren Chrome
// Web Store - Google doi mot dia chi cong khai, khong nhan file trong goi zip.
Route::view('/chinh-sach', 'chinh-sach')->name('chinh-sach');

// Tra cuu lai ma khi lam mat. Ma duoc GUI VE EMAIL chu khong hien ra trang -
// xem chu thich o TraCuuController.
Route::get('/tra-cuu', [TraCuuController::class, 'form'])->name('tra-cuu');
Route::post('/tra-cuu', [TraCuuController::class, 'gui'])->name('tra-cuu.gui');
