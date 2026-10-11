<?php

namespace App\Mail;

use App\Models\License;
use App\Models\Order;
use Illuminate\Support\Facades\URL;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Gui ma kich hoat cho nguoi mua.
 *
 * Dung chung cho bon viec: cap lan dau, gia han, tra cuu lai khi mat, va nhac
 * sap het han. Bon thu do khac nhau moi cau mo dau, phan ma kich hoat thi y
 * het - nen de chung mot khuon, sua mot cho la ca bon cung dung.
 */
class LicenseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public License $license,
        public string $kieu = 'moi',
        public ?Order $don = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $ten = config("license.products.{$this->license->product}.name", 'sản phẩm');

        return new Envelope(subject: match ($this->kieu) {
            'tra-cuu' => "Mã kích hoạt {$ten} của bạn",
            'nhac' => "Mã kích hoạt {$ten} sắp hết hạn",
            'gia-han' => "Đã gia hạn {$ten} đến {$this->license->expires_at->format('d/m/Y')}",
            default => "Mã kích hoạt {$ten}",
        });
    }

    public function content(): Content
    {
        // Don gia han khong nam o `license->order` (cot do van tro ve don mua
        // lan dau), nen phai nhan don tu ben goi.
        $don = $this->don ?: $this->license->order;

        return new Content(view: 'emails.license', with: [
            'tenSanPham' => config("license.products.{$this->license->product}.name", 'sản phẩm'),
            'soNgay' => $this->license->soNgayConLai(),
            'bienNhan' => $don?->daTra()
                ? URL::signedRoute('bien-nhan', ['ma' => $don->code])
                : null,
        ]);
    }
}
