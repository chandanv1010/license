# Máy chủ license

Bán mã kích hoạt qua chuyển khoản ngân hàng (SePay + VietQR), cấp mã tự động
khi tiền về, gửi mã qua email, nhắc gia hạn. Laravel 13, CSDL mặc định SQLite.

Dùng cho extension `../nopause`, nhưng thêm sản phẩm khác chỉ là thêm một mục
trong `config/license.php`.

## Luồng

```
Khách mở /mua/nopause → bấm "Lấy mã QR"
   → máy chủ tạo đơn, sinh mã LXxxxxxx
   → hiện QR VietQR đã điền sẵn số tiền và mã đơn
   → khách quét, chuyển khoản
   → SePay gọi POST /api/sepay/webhook   ─┐
   → đối chiếu mã + số tiền                ├─ cùng một ThanhToanService
   → sinh license key, gửi email           │
   → trang mua đang hỏi 3 giây/lần, thấy "paid" thì hiện key
                                           │
license:doi-soat (cron 5 phút/lần) ───────┘
   → hỏi SePay xem có giao dịch nào webhook bỏ sót không
```

Gia hạn đi đúng luồng đó, chỉ khác một chỗ: người mua điền **mã cũ** vào trang
mua, và lúc tiền về thì máy chủ **cộng thêm ngày vào chính mã đó** thay vì cấp
mã mới.

⚠️ Chu kỳ bán là **3 tháng**, và chuyển khoản ngân hàng **không tự động gia hạn
được** — khách phải tự chuyển tiền lại bốn lần một năm. Mỗi lần là một cơ hội
để họ bỏ, nên `license:nhac-gia-han` ở đây là thứ giữ doanh thu chứ không phải
tính năng phụ. Muốn đổi chu kỳ thì sửa `days` và `amount` trong
`config/license.php`; không chỗ nào khác viết cứng hai con số đó, kể cả bài
kiểm tra.

## Chạy ở máy

```bash
docker run -d --name license-app -p 8010:80 \
  -v "$PWD":/var/www/html -v license-vendor:/var/www/html/vendor \
  --entrypoint apache2-foreground noxh-app:latest
docker exec license-app chown -R www-data:www-data /var/www/html/vendor /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
docker exec -u www-data -e HOME=/tmp license-app composer install
docker exec -u www-data -e HOME=/tmp license-app php artisan migrate --force
docker exec -u www-data -e HOME=/tmp license-app php artisan test
```

⚠️ **`vendor/` phải nằm trên ổ đĩa của container**, không phải thư mục chia sẻ
với Windows. Để trên thư mục chia sẻ thì mỗi request mất ~3,3 giây thay vì
~270ms, và SePay sẽ **timeout sau 30 giây** rồi coi như giao dịch thất bại.
Chuyện này đã xảy ra thật. Đổi lại: cài thư viện phải chạy **trong** container.

| | Trang |
|---|---|
| Mua / gia hạn | http://localhost:8010/mua/nopause |
| Tra cứu lại mã | http://localhost:8010/tra-cuu |
| Chính sách riêng tư | http://localhost:8010/chinh-sach |
| Quản trị | http://localhost:8010/quan-tri |

Tài khoản quản trị tạo bằng lệnh — mật khẩu hỏi lúc chạy, không phải tham số
dòng lệnh (tham số thì nó nằm lại trong lịch sử shell):

```bash
docker exec -it -u www-data -e HOME=/tmp license-app php artisan license:admin
```

## Cấu hình

```
# --- Webhook (SePay gọi sang mình) ---
SEPAY_WEBHOOK_AUTH=hmac          # hmac | apikey | any
SEPAY_WEBHOOK_SECRET=            # khoá HMAC, SePay cấp khi tạo webhook
SEPAY_WEBHOOK_TOKEN=             # chỉ cần nếu dùng kiểu API key
SEPAY_WEBHOOK_SKEW=300           # lệch đồng hồ tối đa, giây
SEPAY_ALLOWED_IPS=               # để trống = không lọc; xem phần IP bên dưới

# --- API Access (mình gọi sang SePay, chỉ để đối soát) ---
SEPAY_API_BASE=https://userapi-sandbox.sepay.vn   # live: https://userapi.sepay.vn
SEPAY_API_TOKEN=

# --- Tài khoản nhận tiền ---
SEPAY_ACCOUNT=0000000001         # test mode; live đổi thành số thật
SEPAY_BANK=MB
SEPAY_ACCOUNT_NAME="NGUYEN CONG TUAN"

# --- Giá ---
LICENSE_NOPAUSE_QUY=50000
LICENSE_CODE_PREFIX=LX
LICENSE_MAX_DEVICES=3
LICENSE_DEVICE_IDLE_DAYS=14      # máy lâu không dùng thì nhả suất; 0 = tắt
LICENSE_ORDER_TTL=60

# --- Gửi mã qua email ---
MAIL_MAILER=log                  # dev: thư vào storage/logs/laravel.log
                                 # thật: smtp + MAIL_HOST/PORT/USERNAME/PASSWORD
MAIL_FROM_ADDRESS=
```

⚠️ **Để trống cả `SEPAY_WEBHOOK_SECRET` lẫn `SEPAY_WEBHOOK_TOKEN` thì webhook
từ chối tất cả.** Cố ý: thiếu xác thực mà vẫn nhận thì bất kỳ ai biết địa chỉ
webhook đều tự cấp license cho mình.

## Dùng MySQL thay vì SQLite

Mặc định là SQLite — một file, không máy chủ CSDL, không user/password, không
phải sao lưu riêng. Với lượng đơn của sản phẩm này nó thừa sức.

Muốn dùng MySQL (để xem bằng phpMyAdmin, hoặc để aaPanel sao lưu tự động) thì
**không phải sửa dòng code nào** — đổi `.env` rồi chạy migration:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sql_license
DB_USERNAME=
DB_PASSWORD=
```

```bash
php artisan migrate --force
```

Toàn bộ 140 bài kiểm tra đã chạy qua trên MySQL 8.0.46.

⚠️ **Đừng import file `.sql` xuất từ SQLite vào MySQL.** Hai hệ dùng kiểu cột
khác nhau (`PRAGMA` là lệnh riêng của SQLite, và `varchar` không độ dài thì
MySQL từ chối) — phpMyAdmin sẽ báo lỗi `#1064` ngay dòng đầu.

Đã có dữ liệu trong SQLite và muốn mang sang: để `migrate` dựng bảng trước,
rồi chép **dữ liệu** bằng lệnh riêng. Dịch câu lệnh tạo bảng từ hệ này sang hệ
kia là cách sớm muộn cũng sai.

```bash
echo "mat-khau-mysql" | php chuyen-sang-mysql.php 127.0.0.1 sql_license root
```

Lệnh đó tự đếm lại sau khi chép — chép xong mà không đối chiếu thì một bảng
lặng lẽ không vào được cũng không ai biết.

## Lệnh chạy theo lịch

Máy chủ phải có **một** dòng cron, nếu không thì không lịch nào chạy:

```
* * * * * cd /duong/dan && php artisan schedule:run >> /dev/null 2>&1
```

| Lệnh | Khi nào | Việc |
|---|---|---|
| `license:doi-soat` | 5 phút/lần | Hỏi SePay xem webhook có bỏ sót giao dịch nào không |
| `license:nhac-gia-han` | 9h hằng ngày | Nhắc trước khi hết hạn 7 ngày |
| `license:don-don` | 15 phút/lần | Xoá đơn chưa trả quá hạn |

Cả ba đều có `--thu` để xem trước mà không ghi gì.

**`license:doi-soat` là lưới an toàn, không phải thứ tuỳ chọn.** Webhook là một
cú gọi HTTP qua Internet và nó rớt được — đã rớt ngay lần thử đầu tiên. Khách
chuyển tiền mà không nhận được mã thì họ nghĩ mình lừa đảo, chứ không nghĩ
"chắc webhook rớt".

## Vòng đời một mã kích hoạt

```
tiền về → sinh key NOPAUSE-XXXX-XXXX-XXXX, hạn 90 ngày, tối đa 3 máy
   → gửi về email người mua
   → dán vào extension, extension gọi /api/license/verify kèm id máy
   → máy chưa có trong danh sách thì chiếm một suất
   → extension hỏi lại mỗi 24 giờ (giữ last_seen_at luôn mới)
   → trước khi hết hạn 7 ngày: email nhắc gia hạn
   → hết hạn: verify trả `expired`, extension quay về 5 lượt miễn phí
```

Thu hồi là đặt `revoked_at` — lần hỏi kế tiếp trả `revoked`. Dùng cho hoàn
tiền hoặc giao dịch gian lận.

### Dán cho ai cũng chạy được không?

Không. Mỗi key chạy tối đa `LICENSE_MAX_DEVICES` máy; máy thứ tư bị từ chối.
Nhưng cần nói rõ đây là **hàng rào ma sát, không phải khoá**:

| | |
|---|---|
| Dán cho bạn bè | Chặn được — ba người đầu chiếm chỗ, người thứ tư bị từ chối |
| Dán lên diễn đàn | Chặn được — cùng lý do |
| Người dùng tự sửa code extension | **Không chặn được** |

Điều cuối là sự thật của mọi extension trình duyệt: code chạy trên máy người
dùng, ai cũng tải về sửa được. Không hệ thống license nào trong trình duyệt
ngăn được việc đó. Giữ cho đa số không muốn lách là đủ — người bỏ công sửa
code vốn đã không định trả tiền.

### Nhả suất máy

Id máy do extension sinh và nằm trong `chrome.storage`. Cài lại tiện ích, đổi
máy, hay dọn dẹp trình duyệt là sinh id **mới** — máy cũ biến mất vĩnh viễn
nhưng vẫn giữ suất.

Không nhả thì **người mua thật** bị khoá ở lần đổi máy thứ tư, còn kẻ chia sẻ
key chẳng hề hấn gì. Vì thế: máy không thấy mặt quá `LICENSE_DEVICE_IDLE_DAYS`
ngày thì nhường suất cho máy mới, có ghi vào nhật ký.

Máy **đang** dùng không bị nhả — extension hỏi lại mỗi 24 giờ nên
`last_seen_at` luôn mới. Nhờ vậy kẻ chia sẻ key không thể đẩy nhau ra vào vô
hạn.

## Trang quản trị

Một tài khoản duy nhất. Không có đăng ký, không có quên mật khẩu — mỗi đường
dẫn thêm vào là một chỗ nữa phải nhớ mà khoá, trong khi chỉ có một người dùng.

| Trang | Việc |
|---|---|
| Tổng quan | Doanh thu tháng, license đang dùng, sắp hết hạn, **giao dịch chưa khớp** |
| Đơn hàng | Tìm theo mã đơn hoặc email, lọc theo trạng thái |
| Giao dịch | Mọi giao dịch tiền vào, lọc riêng những cái chưa khớp đơn |
| License | Thu hồi, khôi phục, gỡ máy, cộng ngày, gửi lại mã |

Phần lớn việc ở đây là cho những ca **không tự động được**:

| Ca | Làm gì |
|---|---|
| Khách gõ sai nội dung chuyển khoản | Giao dịch → gắn vào đơn → cấp mã ngay |
| Khách đổi máy lần thứ tư | License → gỡ một máy |
| Hoàn tiền | License → thu hồi |
| Máy chủ sập, đền bù | License → cộng ngày |
| Khách bảo không nhận được thư | License → gửi lại mã |

Mọi việc sửa dữ liệu đều là **POST**, không bao giờ GET. Một đường dẫn GET thu
hồi license nghĩa là chỉ cần lừa người đang đăng nhập bấm vào một tấm ảnh là
license bay mất.

Đăng nhập sai 5 lần thì khoá IP đó 5 phút, và câu báo lỗi **giống hệt nhau**
dù email có thật hay không — khác nhau là biến ô đăng nhập thành công cụ dò
xem địa chỉ nào có tài khoản quản trị.

## Biên nhận

Mỗi đơn đã trả có một trang biên nhận in được, đường dẫn kèm trong thư gửi mã.
Đường dẫn được **ký**: mã đơn chỉ 8 ký tự, không ký thì dò mã là xem được biên
nhận — và email — của người khác.

Chữ ký **không đặt hạn**. Biên nhận là thứ người ta lưu lại để đòi tiền ở công
ty, có thể vài tháng sau mới dùng đến; một đường dẫn hết hạn sau bảy ngày là
một đường dẫn vô dụng.

⚠️ Đây **không phải hoá đơn đỏ (VAT)** — trang biên nhận có nói rõ điều đó.
Khách doanh nghiệp cầm nó đi quyết toán thuế rồi bị trả lại thì đó là lỗi của
mình, không phải của họ.

## Chặn đơn rác

Phần lớn người mở trang mua **không** chuyển tiền. Không chặn thì bảng `orders`
phình ra bằng rác, và một vòng lặp `curl` đổ đầy bảng trong vài phút. Ba lớp:

| Lớp | Việc |
|---|---|
| Dùng lại đơn trong phiên | Bấm lại bao nhiêu lần cũng nhận **đúng** đơn cũ — một người, một đơn |
| Chặn dòng theo IP | Tạo đơn 10 lần/phút, hỏi trạng thái 120 lần/phút |
| `license:don-don` | 15 phút/lần, xoá đơn chưa trả quá `LICENSE_ORDER_TTL` |

Khoá theo **phiên** chứ không theo IP. Cả một văn phòng ra Internet chung một
địa chỉ; khoá theo IP là hai người khác nhau cùng trỏ vào một đơn, và người thứ
hai chuyển tiền xong sẽ nhận đúng cái mã của người thứ nhất.

Vì thế `/api/order` nằm ở `routes/web.php` chứ không ở `routes/api.php` — nó
cần phiên làm việc, và kèm theo đó là thẻ CSRF.

⚠️ **Lệnh dọn không đụng tới đơn đã có giao dịch**, dù đơn vẫn ở trạng thái chưa
trả. Trường hợp thật: khách chuyển **thiếu** tiền. Giao dịch đã được ghi và trỏ
vào đơn đó; xoá đơn đi là cắt đứt sợi dây duy nhất nối số tiền ấy với người đã
chuyển, còn lại một dòng tiền không biết của ai.

Và kể cả khi đơn đã bị xoá mà tiền mới về, **giao dịch vẫn được lưu** kèm ghi
chú "không ứng với đơn nào" — tiền không bao giờ rơi vào khoảng không.

## Nối với SePay

1. Trang quản trị SePay → thêm webhook
   - URL: `https://<tên-miền>/api/sepay/webhook` — **nhớ cả đường dẫn**, thiếu
     là SePay gọi vào `/` và không bao giờ tới được ứng dụng
   - Loại giao dịch: **Tiền vào**
   - Định dạng: **JSON**
   - Bật **"tự động gửi lại khi server trả lỗi"**
   - Bảo mật: **HMAC-SHA256**, chép Secret Key vào `.env`
2. Thử bằng form **"Mô phỏng giao dịch"** trong Test mode, nội dung đặt bằng
   một mã đơn thật

**Lúc phát triển, SePay không gọi vào `localhost` được** — nó từ chối ngay khi
lưu URL. Mở đường hầm:

```bash
docker run -d --name sepay-tunnel --add-host=host.docker.internal:host-gateway \
  cloudflare/cloudflared:latest tunnel --no-autoupdate --url http://host.docker.internal:8010
docker logs sepay-tunnel | grep trycloudflare.com
```

Địa chỉ đó **đổi mỗi lần khởi động lại**.

### Chuyển từ Test mode sang Live

Hai môi trường **cách ly hoàn toàn**. Tắt Test mode **không** biến webhook test
thành webhook live — phải tạo lại từ đầu trong Live mode, với secret mới. Đổi
luôn `SEPAY_API_BASE`, `SEPAY_ACCOUNT` và `SEPAY_WEBHOOK_SECRET`.

### Lọc theo IP

SePay gửi từ **7 IPv4 + 2 IPv6** cố định. Khai vào `SEPAY_ALLOWED_IPS` là chặn
được người ngoài dù họ biết địa chỉ webhook.

**Mặc định tắt**, vì chạy sau proxy (nginx trước apache của aaPanel) thì IP đọc
được là của proxy chứ không phải của SePay — bật khi chưa khai báo proxy là
chặn nhầm sạch. Mỗi request bị từ chối đều ghi IP thật vào
`storage/logs/laravel.log`; xem rồi hãy bật.

## Vì sao code trông "thừa cẩn thận"

Mỗi chỗ đều từng là một cách mất tiền hoặc mất khách:

| Chỗ | Nếu làm ẩu |
|---|---|
| Xác thực webhook | Ai cũng tự cấp license cho mình |
| HMAC ký trên **thân gốc** | `json_encode` lại ra chuỗi khác → chữ ký không bao giờ khớp |
| Chống phát lại (5 phút) | Bắt được một gói tin hợp lệ là phát lại mãi mãi |
| **Dò mã trong chuỗi**, không so sánh bằng | Ngân hàng chèn đầy chữ vào nội dung → không đơn nào khớp |
| Hai mã hợp lệ cùng lúc thì bỏ qua | Cấp nhầm hàng cho người khác |
| Chống trùng theo **cả** `sepay_id` **lẫn** `reference_code` | Webhook trả id dạng số, API trả UUID → đối soát ghi lại giao dịch đã xử lý |
| Khoá dòng khi ghi nhận | Hai giao dịch cùng lúc → hai license |
| Lưu **mọi** giao dịch kể cả không khớp | Khách kêu "tôi chuyển rồi" mà không có chỗ nào tra |
| Bấm lại trả về **đúng** đơn cũ | Khách quét mã QR cũ rồi, đổi mã là họ chuyển một đằng trang chờ một nẻo |
| Dọn đơn **chừa** đơn đã có giao dịch | Khách chuyển thiếu → xoá mất sợi dây nối tiền với người gửi |
| Chuyển thiếu không cấp, chuyển thừa vẫn cấp | Gõ nhầm số tiền không phải lý do bắt khách chờ |
| Giới hạn số máy | Một key dán lên diễn đàn là cả thế giới dùng chung |
| Nhả suất máy đã lâu không dùng | Khách cài lại extension ba lần là bị khoá khỏi thứ họ đã mua |
| Gia hạn **cộng ngày** vào mã cũ | Cấp mã mới là khách phải nhập lại ở từng máy, và mã cũ thành rác |
| Gia hạn sớm tính từ **hạn cũ** | Gia hạn sớm ba tháng mà mất trắng ba tháng thì không ai gia hạn sớm nữa |
| Mã gia hạn sai thì **báo lỗi** | Lặng lẽ tạo đơn mua mới là khách trả tiền xong vẫn hết hạn như cũ |
| Mọi việc sửa ở quản trị là POST | Một đường dẫn GET thu hồi license là lừa một cú bấm làm bay license |
| Đường dẫn biên nhận được ký | Mã đơn 8 ký tự — dò mã là đọc được email người khác |
| Gửi mail **sau khi** commit | Giao dịch cuộn ngược → khách cầm một mã không tồn tại |
| Mail hỏng không làm hỏng việc cấp | Khách trả tiền xong không thấy gì, dù license đã nằm trong CSDL |
| `/tra-cuu` **gửi về email**, không hiện ra trang | Ai đoán trúng email là lấy được mã |
| `/tra-cuu` trả lời giống nhau dù email có hay không | Thành công cụ dò xem ai đã mua hàng |
| `/verify` luôn trả 200 kèm `ok` | Extension phân biệt được "key sai" với "máy chủ hỏng" — không khoá nhầm người đã trả tiền |

Chuỗi nội dung chuyển khoản thật của MB, dùng trong bài kiểm tra:

```
MBVCB.16475489034.172405.NGUY THI PHUONG chuyen tien.CT tu 1965763389
NGUYEN THI PHUONG toi 0281119899999 NGUYEN CONG TUAN tai MB- Ma GD ACS
```

Người gửi chỉ gõ "chuyen tien". Tất cả phần còn lại do ngân hàng chèn.

## Còn thiếu

- [ ] Hoá đơn đỏ (VAT) — phải đi qua cơ quan thuế, không làm thay được ở đây.
- [ ] Nhiều tài khoản quản trị với phân quyền. Hiện một tài khoản làm mọi việc.
- [ ] Xuất báo cáo doanh thu ra file.
