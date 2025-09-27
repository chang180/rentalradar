<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Python Configuration
    |--------------------------------------------------------------------------
    |
    | 設定 Python 執行環境和相關參數
    |
    */

    'path' => env('PYTHON_PATH', 'python'),
    
    'timeout' => env('PYTHON_TIMEOUT', 300), // 5 分鐘超時
    
    'memory_limit' => env('PYTHON_MEMORY_LIMIT', '512M'),
    
    'scripts_path' => base_path('.ai-dev/core-tools'),
    
    'temp_path' => storage_path('app/temp'),
    
    'cache_ttl' => env('PYTHON_CACHE_TTL', 3600), // 1 小時快取
    
    'required_packages' => [
        'numpy',
        'pandas', 
        'scikit-learn',
        'matplotlib',
        'seaborn'
    ],
    
    'performance' => [
        'max_execution_time' => 300,
        'max_memory_usage' => '1G',
        'enable_caching' => true,
        'cache_key_prefix' => 'python_'
    ]
];
