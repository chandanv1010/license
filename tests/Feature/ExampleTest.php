<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Trang goc dua thang toi trang mua, khong de nguoi dung lac o trang trong. */
    public function test_trang_goc_dua_toi_trang_mua(): void
    {
        $this->get('/')->assertRedirect('/mua/nopause');
    }
}
