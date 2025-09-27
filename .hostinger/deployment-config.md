# Hostinger 部署配置指南

## 🎯 Hostinger 相容性重點

### 系統需求
- **PHP 8.4+** (Hostinger Premium 支援)
- **Composer 2.x**
- **Node.js 18+ & NPM** (建置用)
- **SQLite** (免 MySQL 配置)
- **Apache/Nginx** (共享主機環境)

## 📁 檔案結構調整

### 1. 公開目錄結構
```
public_html/                    # Hostinger 根目錄
├── index.php                   # Laravel 入口點
├── css/                        # 編譯後 CSS
├── js/                         # 編譯後 JS
├── images/                     # 靜態圖片
└── .htaccess                   # Apache 配置

domain.com/                     # Laravel 應用根目錄
├── app/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env
└── composer.json
```

### 2. 環境配置
```bash
# .env (Hostinger 優化)
APP_NAME=RentalRadar
APP_ENV=production
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com

# 資料庫 (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/your/app/database/database.sqlite

# 快取 (檔案系統)
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# 郵件 (Hostinger SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

## 🔧 Apache 配置

### .htaccess (public_html)
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # 處理授權標頭
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # 重導向至 Laravel
    RewriteCond %{REQUEST_URI} !^/public
    RewriteRule ^(.*)$ /public/$1 [L]
</IfModule>

# 安全性標頭
<IfModule mod_headers.c>
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set Referrer-Policy "same-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>

# 檔案快取
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</IfModule>
```

### .htaccess (public)
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # 處理授權標頭
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # 重導向尾隨斜線
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # 處理前端路由
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

## 🚀 部署腳本

### deploy.sh
```bash
#!/bin/bash

echo "🚀 開始 RentalRadar Hostinger 部署..."

# 1. 備份現有檔案
echo "📦 備份現有檔案..."
cp -r public_html public_html_backup_$(date +%Y%m%d_%H%M%S)

# 2. 安裝 PHP 依賴
echo "📥 安裝 PHP 依賴..."
composer install --no-dev --optimize-autoloader

# 3. 建置前端資源
echo "🏗️ 建置前端資源..."
npm ci
npm run build

# 4. 設定權限
echo "🔐 設定檔案權限..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# 5. 優化 Laravel
echo "⚡ 優化 Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. 執行資料庫遷移
echo "🗄️ 執行資料庫遷移..."
php artisan migrate --force

# 7. 複製檔案到 public_html
echo "📂 複製檔案到 public_html..."
cp -r public/* public_html/
cp index.php public_html/

# 8. 清理暫存檔案
echo "🧹 清理暫存檔案..."
php artisan cache:clear
php artisan config:clear

echo "✅ 部署完成！"
```

## 🔧 效能優化

### 1. PHP 配置優化
```ini
; php.ini 建議設定
memory_limit = 256M
max_execution_time = 300
max_input_vars = 3000
upload_max_filesize = 20M
post_max_size = 25M

; OPcache 優化
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

### 2. Laravel 配置優化
```php
// config/app.php (生產環境)
'debug' => false,
'log_level' => 'error',

// config/cache.php
'default' => 'file',
'stores' => [
    'file' => [
        'driver' => 'file',
        'path' => storage_path('framework/cache/data'),
    ],
],

// config/session.php
'driver' => 'file',
'lifetime' => 120,
'secure' => true,
'http_only' => true,
```

### 3. 前端優化
```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'leaflet': ['leaflet', 'react-leaflet'],
                    'react': ['react', 'react-dom'],
                    'vendor': ['@inertiajs/react']
                }
            }
        },
        chunkSizeWarningLimit: 1000,
        sourcemap: false
    }
});
```

## 🔒 安全性配置

### 1. 環境變數保護
```bash
# 隱藏敏感檔案
echo "Deny from all" > .env.htaccess
echo "Deny from all" > storage/.htaccess
echo "Deny from all" > vendor/.htaccess
```

### 2. SQL 注入防護
```php
// 所有查詢使用 Eloquent 或參數化查詢
Property::where('district', $district)
    ->whereBetween('latitude', [$southLat, $northLat])
    ->get();
```

### 3. XSS 防護
```php
// 自動跳脫輸出
{!! clean(e($userInput)) !!}

// CSP 標頭
'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'"
```

## 📊 監控與維護

### 1. 錯誤日誌監控
```php
// config/logging.php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'error',
        'days' => 14,
        'replace_placeholders' => true,
    ],
],
```

### 2. 效能監控
```php
// 自定義中間件追蹤 API 回應時間
class PerformanceMonitor
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        if ($duration > 2.0) {
            Log::warning('Slow API response', [
                'url' => $request->url(),
                'duration' => $duration
            ]);
        }

        return $response;
    }
}
```

### 3. 資料庫維護
```bash
# 定期最佳化
php artisan db:optimize

# 清理過期快取
php artisan cache:prune-stale-tags

# 清理舊日誌
find storage/logs -name "*.log" -mtime +30 -delete
```

## 🚦 檢查清單

### 部署前檢查
- [ ] `.env` 檔案設定正確
- [ ] 資料庫連線測試
- [ ] 檔案權限設定
- [ ] SSL 憑證安裝
- [ ] DNS 設定指向

### 部署後檢查
- [ ] 網站正常載入
- [ ] API 端點測試
- [ ] 地圖功能正常
- [ ] AI 功能運作
- [ ] 錯誤日誌檢查

### 效能檢查
- [ ] 頁面載入 < 3秒
- [ ] API 回應 < 1秒
- [ ] 地圖載入 < 2秒
- [ ] 記憶體使用 < 100MB
- [ ] CPU 使用率正常

## 📞 技術支援

### Hostinger 專用指令
```bash
# 查看 PHP 版本
php -v

# 查看已安裝擴充功能
php -m

# 測試檔案權限
ls -la storage/

# 查看錯誤日誌
tail -f storage/logs/laravel.log
```

### 常見問題解決
1. **500 錯誤**: 檢查 `.env` 和檔案權限
2. **白畫面**: 啟用 `APP_DEBUG=true` 查看錯誤
3. **CSS/JS 載入失敗**: 檢查 `APP_URL` 設定
4. **API 404**: 確認 `.htaccess` 重寫規則
5. **地圖載入慢**: 啟用 OPcache 和檔案快取