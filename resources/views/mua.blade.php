<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mua {{ $sanPham['name'] }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px 48px;
            font: 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f4f6f9;
            color: #13224a;
        }
        .hop {
            max-width: 420px;
            margin: 0 auto;
            padding: 26px 24px 24px;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 34px rgba(11, 40, 80, .10);
        }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .phu { margin: 0 0 20px; color: #6e7991; font-size: 14px; }
        .goi {
            display: flex; align-items: baseline; justify-content: space-between;
            gap: 10px; padding: 13px 15px; margin-bottom: 16px;
            border: 1px solid #e3e7ec; border-radius: 10px; background: #fafbfc;
        }
        .goi b { font-size: 19px; }
        label { display: block; margin-bottom: 6px; font-size: 13.5px; font-weight: 600; }
        input {
            width: 100%; padding: 11px 13px; font: inherit;
            border: 1px solid #d8dee6; border-radius: 9px; background: #fff; color: inherit;
        }
        input:focus { border-color: #0a78f5; outline: 2px solid rgba(10,120,245,.16); }
        .nhe { color: #8e929e; font-weight: 400; }
        .ghi-chu { margin: 6px 0 16px; color: #8e929e; font-size: 12.5px; }
        button {
            width: 100%; padding: 13px; font: inherit; font-weight: 700;
            border: 0; border-radius: 9px; background: #0a78f5; color: #fff; cursor: pointer;
        }
        button:hover { background: #0b6ee2; }
        button:disabled { background: #9db9dd; cursor: default; }
        .an { display: none; }
        .qr { display: block; width: 240px; max-width: 100%; margin: 4px auto 14px; border-radius: 10px; }
        .dong {
            display: flex; justify-content: space-between; gap: 12px;
            padding: 8px 0; border-bottom: 1px dashed #e8ebef; font-size: 14px;
        }
        .dong span:first-child { color: #6e7991; }
        .dong span:last-child { font-weight: 600; text-align: right; word-break: break-all; }
        .cho { margin: 16px 0 0; text-align: center; color: #6e7991; font-size: 13.5px; }
        .xong { margin-top: 18px; padding: 16px; border-radius: 10px; background: #eafaf0; }
        .xong h2 { margin: 0 0 10px; font-size: 16px; color: #14803c; }
        .key {
            display: block; padding: 12px; border-radius: 8px; background: #fff;
            border: 1px solid #bfe8cd; font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 16px; font-weight: 700; letter-spacing: .5px; text-align: center;
        }
        .loi { margin-top: 14px; padding: 10px 12px; border-radius: 8px; background: #fdecec; color: #dc2626; font-size: 13.5px; }
    </style>
</head>
<body>
<div class="hop">
    <h1>{{ $sanPham['name'] }}</h1>
    <p class="phu">Mua mã kích hoạt, dùng ngay sau khi chuyển khoản.</p>

    @php $maGoi = array_key_first($sanPham['plans']); $goi = $sanPham['plans'][$maGoi]; @endphp

    <div class="goi">
        <span>{{ $goi['label'] }}</span>
        <b>{{ number_format($goi['amount'], 0, ',', '.') }}đ</b>
    </div>

    <div id="buoc-1">
        <label for="email">Email nhận mã</label>
        <input type="email" id="email" placeholder="ban@example.com" autocomplete="email">
        <p class="ghi-chu">Không bắt buộc, nhưng mất mã thì đây là cách duy nhất lấy lại.</p>

        {{-- Mo san khi den tu nut "Gia han ngay" trong thu nhac. --}}
        <label for="key-cu">Mã kích hoạt cũ <span class="nhe">— chỉ điền nếu gia hạn</span></label>
        <input type="text" id="key-cu" placeholder="NOPAUSE-XXXX-XXXX-XXXX"
               value="{{ $keyCu }}" autocomplete="off" spellcheck="false">
        <p class="ghi-chu">Điền vào thì chúng tôi <b>cộng thêm {{ $goi['days'] }} ngày</b> vào chính mã đó,
            không cấp mã mới — bạn không phải nhập lại ở từng máy.</p>

        <button type="button" id="nut-mua">Lấy mã QR chuyển khoản</button>
    </div>

    <div id="buoc-2" class="an">
        <img id="qr" class="qr" alt="Mã QR chuyển khoản">
        <div class="dong"><span>Ngân hàng</span><span id="bank"></span></div>
        <div class="dong"><span>Số tài khoản</span><span id="acc"></span></div>
        <div class="dong"><span>Chủ tài khoản</span><span id="ten"></span></div>
        <div class="dong"><span>Số tiền</span><span id="tien"></span></div>
        <div class="dong"><span>Nội dung</span><span id="ma"></span></div>
        <p class="cho" id="cho">Quét mã bằng app ngân hàng. Trang này tự nhận khi tiền về…</p>
    </div>

    <div id="buoc-3" class="an">
        <div class="xong">
            <h2>Thanh toán thành công</h2>
            <code class="key" id="key"></code>
            <p class="ghi-chu" style="margin:10px 0 0">
                Dán mã này vào extension để kích hoạt. Hạn dùng đến <b id="han"></b>.
            </p>
        </div>
    </div>

    <div id="loi" class="loi an"></div>
</div>

<script>
(function () {
    var SAN_PHAM = @json($maSanPham);
    var GOI = @json($maGoi);

    var nut = document.getElementById('nut-mua');
    var loi = document.getElementById('loi');
    var hen = null;

    // Duong dan tao don nam trong nhom `web` de co phien lam viec, nen no
    // doi kem the CSRF.
    var the = document.querySelector('meta[name="csrf-token"]');

    var baoLoi = function (chu) {
        loi.textContent = chu;
        loi.classList.remove('an');
    };

    var dinhDang = function (so) {
        return new Intl.NumberFormat('vi-VN').format(so) + 'đ';
    };

    nut.addEventListener('click', function () {
        nut.disabled = true;
        loi.classList.add('an');

        fetch(@json(route('order.create')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': the.content,
            },
            body: JSON.stringify({
                product: SAN_PHAM,
                plan: GOI,
                email: document.getElementById('email').value || null,
                renew_key: document.getElementById('key-cu').value.trim() || null,
            }),
        })
            .then(function (r) {
                // Hai ma nay tra ve chu chu khong phai JSON - khong chan thi
                // nguoi dung chi thay "Unexpected token" khong hieu gi.
                if (r.status === 429) {
                    throw new Error('Bạn bấm hơi nhanh. Chờ khoảng một phút rồi thử lại.');
                }
                if (r.status === 419) {
                    throw new Error('Phiên làm việc đã hết hạn. Hãy tải lại trang.');
                }

                return r.json();
            })
            .then(function (d) {
                if (!d.code) throw new Error(d.message || 'Không tạo được đơn.');

                document.getElementById('qr').src = d.qr;
                document.getElementById('bank').textContent = d.bank;
                document.getElementById('acc').textContent = d.account;
                document.getElementById('ten').textContent = d.account_name;
                document.getElementById('tien').textContent = dinhDang(d.amount);
                document.getElementById('ma').textContent = d.code;

                if (d.gia_han) {
                    document.getElementById('cho').textContent =
                        'Quét mã bằng app ngân hàng. Tiền về là chúng tôi cộng thêm hạn vào mã cũ của bạn…';
                }

                document.getElementById('buoc-1').classList.add('an');
                document.getElementById('buoc-2').classList.remove('an');

                doi(d.code);
            })
            .catch(function (e) {
                nut.disabled = false;
                baoLoi(e.message);
            });
    });

    // Hoi may chu 3 giay mot lan. Dung han sau 15 phut de mot tab mo quen
    // khong goi mai mai.
    function doi(ma) {
        var batDau = Date.now();

        hen = setInterval(function () {
            if (Date.now() - batDau > 15 * 60 * 1000) {
                clearInterval(hen);
                document.getElementById('cho').textContent =
                    'Đã quá 15 phút. Nếu bạn đã chuyển khoản, hãy tải lại trang hoặc liên hệ với chúng tôi.';
                return;
            }

            fetch(@json(url('/api/order')) + '/' + ma + '/status', {
                headers: { 'Accept': 'application/json' },
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.status !== 'paid') return;

                    clearInterval(hen);
                    document.getElementById('key').textContent = d.key;
                    document.getElementById('han').textContent = d.expires_at;
                    document.getElementById('buoc-2').classList.add('an');
                    document.getElementById('buoc-3').classList.remove('an');
                })
                .catch(function () {
                    // Mang chap chon thi bo qua lan nay, lan sau hoi lai.
                });
        }, 3000);
    }
})();
</script>
</body>
</html>
