<?php
/**
 * Xuat CSDL SQLite ra mot file .sql doc duoc.
 *
 * Vi sao tu viet thay vi goi sqlite3: anh dockerfile khong co sqlite3, va cai
 * them mot goi vao may chu chi de xuat file mot lan la khong dang.
 *
 * File ra co ca cau truc bang lan du lieu, chay lai duoc bang:
 *     sqlite3 moi.sqlite < xuat.sql
 *
 * ⚠ File nay co EMAIL KHACH va MA KICH HOAT. Khong dua len git, khong gui qua
 *   kenh cong khai. .gitignore da chan san *.sql.
 *
 * Chay:  php xuat-csdl.php [duong/dan/ra.sql]
 */

$raFile = $argv[1] ?? __DIR__ . '/xuat-csdl.sql';
$csdl = __DIR__ . '/database/database.sqlite';

if (!is_file($csdl)) {
    fwrite(STDERR, "Khong thay {$csdl}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $csdl, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$ra = fopen($raFile, 'w');

fwrite($ra, "-- CSDL may chu license\n");
fwrite($ra, '-- Xuat luc ' . date('d/m/Y H:i:s') . "\n");
fwrite($ra, "--\n");
fwrite($ra, "-- Nap lai:  sqlite3 moi.sqlite < " . basename($raFile) . "\n");
fwrite($ra, "--\n");
fwrite($ra, "-- CHUA EMAIL KHACH VA MA KICH HOAT - khong dua len git.\n\n");
fwrite($ra, "PRAGMA foreign_keys = OFF;\nBEGIN TRANSACTION;\n\n");

// Bo bang noi bo cua sqlite va bang phien/hang doi - do la rac tam thoi,
// khong phai du lieu kinh doanh.
$boQua = ['sqlite_sequence', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

$bang = $pdo->query(
    "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

$tong = 0;

foreach ($bang as $b) {
    $ten = $b['name'];

    if (in_array($ten, $boQua, true)) {
        continue;
    }

    fwrite($ra, "-- --- {$ten} " . str_repeat('-', max(0, 56 - strlen($ten))) . "\n");
    fwrite($ra, "DROP TABLE IF EXISTS \"{$ten}\";\n");
    fwrite($ra, $b['sql'] . ";\n");

    $dong = $pdo->query("SELECT * FROM \"{$ten}\"")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dong as $d) {
        $cot = array_map(fn ($c) => '"' . $c . '"', array_keys($d));

        $gia = array_map(function ($v) use ($pdo) {
            if ($v === null) {
                return 'NULL';
            }

            // Quote() lo cho dau nhay don ben trong chuoi. Tu noi chuoi bang
            // tay la mot noi dung chuyen khoan co dau ' lam vo ca file.
            return is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v);
        }, array_values($d));

        fwrite($ra, "INSERT INTO \"{$ten}\" (" . implode(', ', $cot) . ') VALUES (' . implode(', ', $gia) . ");\n");
    }

    // Chi so cua bang, neu co.
    $chiSo = $pdo->query(
        "SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = '{$ten}' AND sql IS NOT NULL"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($chiSo as $sql) {
        fwrite($ra, $sql . ";\n");
    }

    fwrite($ra, "\n");

    printf("  %-22s %4d dòng\n", $ten, count($dong));
    $tong += count($dong);
}

fwrite($ra, "COMMIT;\nPRAGMA foreign_keys = ON;\n");
fclose($ra);

printf("\nĐã xuất %s — %d dòng, %s KB\n", basename($raFile), $tong, number_format(filesize($raFile) / 1024, 1));
