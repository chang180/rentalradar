<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 資料保留政策配置
    |--------------------------------------------------------------------------
    |
    | 定義各種資料的保留期限和清理策略
    |
    */

    'policies' => [
        
        // 核心業務資料
        'properties' => [
            'retention_days' => env('DATA_RETENTION_PROPERTIES_DAYS', 730), // 2年
            'archive_before_delete' => true,
            'priority' => 'high',
            'description' => '租屋資料 - 核心業務資料',
        ],
        
        // AI 分析資料
        'predictions' => [
            'retention_days' => env('DATA_RETENTION_PREDICTIONS_DAYS', 365), // 1年
            'archive_before_delete' => true,
            'priority' => 'medium',
            'description' => '價格預測資料',
        ],
        
        'recommendations' => [
            'retention_days' => env('DATA_RETENTION_RECOMMENDATIONS_DAYS', 365), // 1年
            'archive_before_delete' => true,
            'priority' => 'medium',
            'description' => '推薦資料',
        ],
        
        'risk_assessments' => [
            'retention_days' => env('DATA_RETENTION_RISK_ASSESSMENTS_DAYS', 365), // 1年
            'archive_before_delete' => true,
            'priority' => 'medium',
            'description' => '風險評估資料',
        ],
        
        // 系統監控資料
        'anomalies' => [
            'retention_days' => env('DATA_RETENTION_ANOMALIES_DAYS', 180), // 6個月
            'archive_before_delete' => false,
            'priority' => 'low',
            'description' => '異常檢測資料',
        ],
        
        // 檔案管理
        'file_uploads' => [
            'retention_days' => env('DATA_RETENTION_FILE_UPLOADS_DAYS', 90), // 3個月
            'archive_before_delete' => false,
            'priority' => 'low',
            'description' => '檔案上傳記錄',
        ],
        
        // 系統排程
        'schedule_executions' => [
            'retention_days' => env('DATA_RETENTION_SCHEDULE_EXECUTIONS_DAYS', 30), // 1個月
            'archive_before_delete' => false,
            'priority' => 'low',
            'description' => '排程執行記錄',
        ],
        
        // 快取資料
        'cache' => [
            'retention_days' => env('DATA_RETENTION_CACHE_DAYS', 7), // 7天
            'archive_before_delete' => false,
            'priority' => 'low',
            'description' => '快取資料',
        ],
        
        // 會話資料
        'sessions' => [
            'retention_days' => env('DATA_RETENTION_SESSIONS_DAYS', 1), // 1天
            'archive_before_delete' => false,
            'priority' => 'low',
            'description' => '使用者會話',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 檔案清理配置
    |--------------------------------------------------------------------------
    |
    | 定義各種檔案的清理策略
    |
    */

    'file_cleanup' => [
        'government_data' => [
            'retention_days' => env('FILE_RETENTION_GOVERNMENT_DATA_DAYS', 7),
            'path' => 'government-data',
            'description' => '政府資料檔案',
        ],
        
        'uploads' => [
            'retention_days' => env('FILE_RETENTION_UPLOADS_DAYS', 30),
            'path' => 'uploads',
            'description' => '使用者上傳檔案',
        ],
        
        'logs' => [
            'retention_days' => env('FILE_RETENTION_LOGS_DAYS', 7),
            'path' => 'logs',
            'description' => '系統日誌檔案',
        ],
        
        'archives' => [
            'retention_days' => env('FILE_RETENTION_ARCHIVES_DAYS', 365),
            'path' => 'archives',
            'description' => '歸檔資料',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 清理排程配置
    |--------------------------------------------------------------------------
    |
    | 定義自動清理的排程設定
    |
    */

    'schedule' => [
        'daily' => [
            'enabled' => env('DATA_RETENTION_DAILY_ENABLED', true),
            'time' => env('DATA_RETENTION_DAILY_TIME', '01:00'),
            'tables' => ['cache', 'sessions'],
        ],
        
        'weekly' => [
            'enabled' => env('DATA_RETENTION_WEEKLY_ENABLED', true),
            'day' => env('DATA_RETENTION_WEEKLY_DAY', 0), // 週日
            'time' => env('DATA_RETENTION_WEEKLY_TIME', '01:30'),
            'tables' => ['file_uploads', 'schedule_executions', 'anomalies'],
        ],
        
        'monthly' => [
            'enabled' => env('DATA_RETENTION_MONTHLY_ENABLED', true),
            'day' => env('DATA_RETENTION_MONTHLY_DAY', 1), // 每月1號
            'time' => env('DATA_RETENTION_MONTHLY_TIME', '02:00'),
            'tables' => ['predictions', 'recommendations', 'risk_assessments'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 歸檔配置
    |--------------------------------------------------------------------------
    |
    | 定義資料歸檔的設定
    |
    */

    'archive' => [
        'enabled' => env('DATA_RETENTION_ARCHIVE_ENABLED', true),
        'path' => env('DATA_RETENTION_ARCHIVE_PATH', 'archives'),
        'format' => env('DATA_RETENTION_ARCHIVE_FORMAT', 'json'),
        'compression' => env('DATA_RETENTION_ARCHIVE_COMPRESSION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 通知配置
    |--------------------------------------------------------------------------
    |
    | 定義清理結果的通知設定
    |
    */

    'notifications' => [
        'enabled' => env('DATA_RETENTION_NOTIFICATIONS_ENABLED', true),
        'channels' => ['mail', 'slack'],
        'recipients' => [
            'admin' => env('DATA_RETENTION_ADMIN_EMAIL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 安全配置
    |--------------------------------------------------------------------------
    |
    | 定義清理過程中的安全設定
    |
    */

    'safety' => [
        'dry_run_enabled' => env('DATA_RETENTION_DRY_RUN_ENABLED', true),
        'confirmation_required' => env('DATA_RETENTION_CONFIRMATION_REQUIRED', true),
        'backup_before_delete' => env('DATA_RETENTION_BACKUP_BEFORE_DELETE', true),
        'max_deletion_per_batch' => env('DATA_RETENTION_MAX_DELETION_BATCH', 1000),
    ],

];
