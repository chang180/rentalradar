<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 確保在測試環境中運行，強制覆蓋環境設定
        $this->app['env'] = 'testing';
        config(['app.env' => 'testing']);
        
        // 強制設定測試資料庫連接
        config(['database.default' => 'testing']);
    }
}
