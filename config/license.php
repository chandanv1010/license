<?php

/**
 * Cau hinh may chu license.
 *
 * Moi con so o day deu doc tu .env de doi gia khong phai sua ma nguon va
 * khong phai trien khai lai.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | San pham va goi ban
    |--------------------------------------------------------------------------
    |
    | `amount` tinh bang DONG, so nguyen. Khong dung so thuc cho tien: 0.1 + 0.2
    | trong so thuc khong bang 0.3, va doi chieu tien chuyen khoan ma lech mot
    | dong la don hang khong bao gio khop.
    |
    | `days` la so ngay license con hieu luc ke tu luc thanh toan.
    |
    | Chu y khi doi chu ky ban: chuyen khoan ngan hang KHONG tu dong gia han
    | duoc. Chu ky cang ngan thi khach cang phai tu chuyen tien nhieu lan mot
    | nam, va moi lan la mot co hoi de ho bo. Lenh `license:nhac-gia-han` vi
    | the la thu giu doanh thu, khong phai tinh nang phu.
    |
    */
    'products' => [
        'nopause' => [
            'name' => 'NoPause Playback',
            'plans' => [
                'quy' => [
                    'label' => 'Gói 3 tháng',
                    'amount' => (int) env('LICENSE_NOPAUSE_QUY', 50000),
                    'days' => 90,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ma don hang
    |--------------------------------------------------------------------------
    |
    | Ma nay di vao NOI DUNG CHUYEN KHOAN. Ngan hang tu chen them rat nhieu chu
    | vao truong do - giao dich thu nghiem that tren MB tra ve:
    |
    |   MBVCB.16475489034.172405.NGUY THI PHUONG chuyen tien.CT tu 1965763389
    |   NGUYEN THI PHUONG toi 0281119899999 NGUYEN CONG TUAN tai MB- Ma GD ACS
    |
    | Nguoi gui chi go "chuyen tien", phan con lai la ngan hang chen. Vi vay
    | KHONG BAO GIO so sanh bang; phai do ma nam trong chuoi.
    |
    | Bang chu cai bo 0 O 1 I de nguoi doc khong nhin nham, va de ma khong trung
    | voi cac day so ngan hang chen vao.
    |
    */
    'code' => [
        'prefix' => env('LICENSE_CODE_PREFIX', 'LX'),
        'length' => 6,
        'alphabet' => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
    ],

    /*
    |--------------------------------------------------------------------------
    | So may toi da cho mot license
    |--------------------------------------------------------------------------
    |
    | Khong gioi han thi mot key bi dan len dien dan la ca the gioi dung chung.
    | De 3 cho nguoi mua that dung duoc may ban, may o co quan, may o nha.
    |
    */
    'max_devices' => (int) env('LICENSE_MAX_DEVICES', 3),

    /*
    |--------------------------------------------------------------------------
    | Nhuong cho may da lau khong dung
    |--------------------------------------------------------------------------
    |
    | Id may do extension sinh ra va nam trong chrome.storage. Cai lai tien
    | ich, doi may, hay don dep trinh duyet la sinh id MOI - may cu van giu
    | suat nhung khong bao gio quay lai nua.
    |
    | Khong nha suat thi nguoi mua that bi khoa o lan doi may thu tu, con ke
    | chia se key thi chang anh huong gi. Sau ngan nay ngay khong thay mat,
    | suat duoc nhuong cho may moi.
    |
    | De 0 de tat.
    |
    */
    'device_idle_days' => (int) env('LICENSE_DEVICE_IDLE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Ben ban - in tren bien nhan
    |--------------------------------------------------------------------------
    |
    | Bien nhan KHONG phai hoa don do (VAT). Do la chung tu ben ban tu phat de
    | khach co cai luu, va de ho doi tien o cong ty neu mua bang tien tui.
    | Muon hoa don do thi phai di qua co quan thue - khong lam thay duoc o day.
    |
    */
    'seller' => [
        'name' => env('SELLER_NAME', 'NoPause'),
        'email' => env('SELLER_EMAIL', env('MAIL_FROM_ADDRESS', '')),
        'site' => env('SELLER_SITE', ''),
        'tax_code' => env('SELLER_TAX_CODE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Don hang het han
    |--------------------------------------------------------------------------
    |
    | Don chua tra sau ngan nay phut thi coi nhu bo. Giu de ma don duoc tai su
    | dung va de bang khong phinh ra vo han.
    |
    */
    'order_ttl_minutes' => (int) env('LICENSE_ORDER_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | SePay
    |--------------------------------------------------------------------------
    |
    | `token` la chuoi SePay gui kem moi webhook o header Authorization. Thieu
    | no thi BAT KY AI biet dia chi webhook deu tu cap license cho minh.
    |
    */
    'sepay' => [
        /*
         | Cach xac thuc webhook:
         |   'any'    - nhan ca HMAC lan API key, cai nao cau hinh thi kiem cai do
         |   'hmac'   - CHI nhan HMAC (SePay khuyen dung)
         |   'apikey' - chi nhan API key
         */
        'auth' => env('SEPAY_WEBHOOK_AUTH', 'any'),

        'token' => env('SEPAY_WEBHOOK_TOKEN'),

        /*
         | Khoa bi mat cho HMAC-SHA256.
         |
         | Hon API key o cho: khoa KHONG di tren duong truyen, va moi request
         | mang mot dau thoi gian nen khong phat lai duoc. SePay ky chuoi
         | "{timestamp}.{raw_body}" roi gui chu ky o header X-SePay-Signature
         | dang "sha256=<hex>", kem X-SePay-Timestamp.
         */
        'secret' => env('SEPAY_WEBHOOK_SECRET'),

        /* Lech dong ho toi da, tinh bang giay. SePay khuyen 5 phut. */
        'skew' => (int) env('SEPAY_WEBHOOK_SKEW', 300),

        /*
         | Danh sach IP cua SePay, cach nhau bang dau phay. De TRONG thi khong
         | kiem - va day la mac dinh, vi dung sau proxy (aaPanel chay nginx
         | truoc apache) thi IP doc duoc la cua proxy chu khong phai cua SePay,
         | bat len ma chua khai bao proxy la chan nham het.
         |
         | Nhat ky luon ghi IP thuc su nhin thay, xem truoc roi hay bat.
         */
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SEPAY_ALLOWED_IPS', '')),
        ))),
        /*
         | API Access - chieu NGUOC voi webhook: may chu minh goi sang SePay.
         | Chi dung cho viec doi soat; webhook khong can toi no.
         |
         | Token Test mode chi xac thuc duoc tren host sandbox, va nguoc lai.
         */
        'api_base' => rtrim((string) env('SEPAY_API_BASE', 'https://userapi.sepay.vn'), '/'),
        'api_token' => env('SEPAY_API_TOKEN'),

        'account' => env('SEPAY_ACCOUNT'),
        'bank' => env('SEPAY_BANK', 'MB'),
        'account_name' => env('SEPAY_ACCOUNT_NAME'),
        'qr_base' => env('SEPAY_QR_BASE', 'https://qr.sepay.vn/img'),
    ],

];
