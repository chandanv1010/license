<?php
/**
 * Chuyen du lieu tu SQLite sang MySQL.
 *
 * KHONG dich lenh tao bang tu SQLite sang MySQL - cach do sai som muon, vi
 * hai he dung kieu cot khac nhau (SQLite co "varchar" khong do dai, MySQL thi
 * phai co). Thay vao do:
 *
 *   1. `php artisan migrate` tu dung bang chuan tren MySQL
 *   2. Script nay chi chep DU LIEU sang
 *
 * Mat khau MySQL doc tu dau vao chuan, khong phai tham so dong lenh - tham so
 * thi no nam lai trong lich su shell va trong danh sach tien trinh.
 *
 * Chay:
 *   echo "$MAT_KHAU" | php chuyen-sang-mysql.php <host> <csdl> <user>
 */

[$script, $host, $csdl, $nguoiDung] = $argv + [null, null, null, null];

if (!$host || !$csdl || !$nguoiDung) {
    fwrite(STDERR, "Dung: echo \"mat-khau\" | php chuyen-sang-mysql.php <host> <csdl> <user>\n");
    exit(1);
}

$matKhau = rtrim((string) fgets(STDIN), "\r\n");

if ($matKhau === '') {
    fwrite(STDERR, "Khong doc duoc mat khau tu dau vao chuan.\n");
    exit(1);
}

$nguon = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$dich = new PDO("mysql:host={$host};dbname={$csdl};charset=utf8mb4", $nguoiDung, $matKhau, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Thu tu nay chi de cho de doc. Khoa ngoai tat ben duoi vi `orders` tro vao
// `licenses` ma `licenses` cung tro nguoc lai `orders` - khong co thu tu nao
// chen duoc neu de khoa ngoai bat.
$bang = ['users', 'orders', 'licenses', 'license_devices', 'payments'];

$dich->exec('SET FOREIGN_KEY_CHECKS = 0');

$tong = 0;

foreach ($bang as $ten) {
    // Bang nao khong co ben nguon thi bo qua, dung do vo.
    $coKhong = $nguon->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = '{$ten}'"
    )->fetchColumn();

    if (!$coKhong) {
        printf("  %-18s khong co ben nguon\n", $ten);
        continue;
    }

    $dich->exec("TRUNCATE TABLE `{$ten}`");

    $dong = $nguon->query("SELECT * FROM \"{$ten}\"")->fetchAll(PDO::FETCH_ASSOC);

    if (!$dong) {
        printf("  %-18s %4d dòng\n", $ten, 0);
        continue;
    }

    $cot = array_keys($dong[0]);
    $cauHoi = '(' . implode(', ', array_fill(0, count($cot), '?')) . ')';
    $sql = "INSERT INTO `{$ten}` (`" . implode('`, `', $cot) . "`) VALUES {$cauHoi}";
    $lenh = $dich->prepare($sql);

    foreach ($dong as $d) {
        $lenh->execute(array_values($d));
    }

    printf("  %-18s %4d dòng\n", $ten, count($dong));
    $tong += count($dong);
}

$dich->exec('SET FOREIGN_KEY_CHECKS = 1');

// Doi chieu lai sau khi chep: chep xong ma khong dem lai thi mot bang im lang
// khong vao duoc cung khong ai biet.
$lech = 0;

foreach (['orders', 'licenses', 'license_devices', 'payments'] as $ten) {
    $a = (int) $nguon->query("SELECT COUNT(*) FROM \"{$ten}\"")->fetchColumn();
    $b = (int) $dich->query("SELECT COUNT(*) FROM `{$ten}`")->fetchColumn();

    if ($a !== $b) {
        printf("  LỆCH %s: nguồn %d, đích %d\n", $ten, $a, $b);
        $lech++;
    }
}

if ($lech) {
    fwrite(STDERR, "\nCHƯA chép xong - xem các dòng LỆCH ở trên.\n");
    exit(1);
}

printf("\nĐã chép %d dòng sang MySQL, đếm lại khớp.\n", $tong);
