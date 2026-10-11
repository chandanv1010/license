<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Tao hoac doi mat khau tai khoan quan tri.
 *
 * Mat khau duoc HOI TRONG LUC CHAY, khong phai tham so dong lenh. Dua vao
 * tham so la no nam lai trong `~/.bash_history` va trong danh sach tien trinh
 * - bat ky ai dang nhap duoc may chu deu doc duoc.
 */
class TaoAdmin extends Command
{
    protected $signature = 'license:admin {email? : Dia chi email dang nhap}';

    protected $description = 'Tạo tài khoản quản trị, hoặc đổi mật khẩu tài khoản đã có';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email đăng nhập');

        $kiem = Validator::make(['email' => $email], ['email' => 'required|email|max:191']);

        if ($kiem->fails()) {
            $this->error('Email không hợp lệ.');

            return self::FAILURE;
        }

        $daCo = User::where('email', $email)->first();

        if ($daCo && !$this->confirm("Tài khoản {$email} đã có. Đổi mật khẩu?", true)) {
            return self::SUCCESS;
        }

        // `secret` khong in ra man hinh, ke ca khi quay man hinh hay ngoi canh.
        $matKhau = $this->secret('Mật khẩu mới (ít nhất 10 ký tự)');

        if (strlen((string) $matKhau) < 10) {
            $this->error('Mật khẩu quá ngắn. Trang này mở ra Internet, 10 ký tự là mức tối thiểu.');

            return self::FAILURE;
        }

        if ($matKhau !== $this->secret('Nhập lại mật khẩu')) {
            $this->error('Hai lần nhập không khớp.');

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $daCo->name ?? 'Quản trị', 'password' => $matKhau],
        );

        $this->info($daCo ? "Đã đổi mật khẩu cho {$email}." : "Đã tạo tài khoản {$email}.");
        $this->line('Đăng nhập tại /quan-tri');

        return self::SUCCESS;
    }
}
