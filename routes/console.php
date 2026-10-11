<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Lich chay dinh ky
|--------------------------------------------------------------------------
|
| May chu phai co MOT dong cron goi vao day, neu khong thi khong lich nao chay:
|
|     * * * * * cd /duong/dan/du-an && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Luoi an toan cho webhook. Webhook la mot cu goi HTTP qua Internet va no ROT
// DUOC - da xay ra ngay lan thu dau tien. Khach chuyen tien ma khong nhan duoc
// ma thi ho nghi minh lua dao, chu khong nghi "chac webhook rot".
Schedule::command('license:doi-soat')->everyFiveMinutes()->withoutOverlapping();

// Nhac gia han truoc bay ngay. Khong nhac thi gan nhu 100% khach roi o thang
// thu 12 - day la chenh lech giua "ban mot lan" va "ban hang nam".
Schedule::command('license:nhac-gia-han')->dailyAt('09:00');

// Don don chua tra qua han cho bang khoi phinh. Chay 15 phut mot lan chu
// khong phai moi gio: neu bi doi don rac thi mot tieng la du de dong rac chat
// cao, con don that thi dang nao cung song het `order_ttl_minutes`.
Schedule::command('license:don-don')->everyFifteenMinutes();
