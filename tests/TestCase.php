<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Goi dang ban. Doi goi chi phai sua MOT cho nay.
     */
    protected const GOI = 'quy';

    /**
     * Gia va so ngay doc tu config, khong viet cung vao bai kiem tra.
     *
     * Truoc day moi bai deu viet thang 200000 va 365. Doi gia mot lan la ba
     * muoi cho phai sua tay, va sua sot mot cho thi bai kiem tra do bao loi o
     * noi khong lien quan gi den thu vua doi - mat ca buoi moi tim ra.
     */
    protected function gia(): int
    {
        return (int) config('license.products.nopause.plans.' . static::GOI . '.amount');
    }

    protected function soNgay(): int
    {
        return (int) config('license.products.nopause.plans.' . static::GOI . '.days');
    }
}
