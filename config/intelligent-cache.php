<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Intelligent Cache Layers
    |--------------------------------------------------------------------------
    |
    | IntelligentCacheService 依查詢熱度將行政區資料分成 hot/warm/cold/temp
    | 四層快取。hot/warm 層預設用 Redis 以支援模式清除（Redis::keys）；
    | 若目前環境沒有可用的 Redis（例如測試環境），可用下列 env 覆寫成
    | 'array' 或 'database'，服務會依 store 名稱自動略過 Redis 專屬呼叫。
    |
    */

    'layers' => [
        'hot' => [
            'store' => env('INTELLIGENT_CACHE_HOT_STORE', 'redis'),
            'ttl' => 3600, // 1小時
            'description' => '熱門行政區資料',
        ],
        'warm' => [
            'store' => env('INTELLIGENT_CACHE_WARM_STORE', 'redis'),
            'ttl' => 1800, // 30分鐘
            'description' => '一般行政區資料',
        ],
        'cold' => [
            'store' => 'database',
            'ttl' => 7200, // 2小時
            'description' => '冷門行政區資料',
        ],
        'temp' => [
            'store' => 'array',
            'ttl' => 300, // 5分鐘
            'description' => '臨時計算結果',
        ],
    ],

];
