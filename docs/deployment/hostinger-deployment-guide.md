# RentalRadar Hostinger 部署指南

## 📋 專案概況

**專案名稱**: RentalRadar  
**技術棧**: Laravel 12 + Inertia.js v2 + React 19 + Tailwind CSS v4  
**資料庫**: SQLite  
**部署目標**: Hostinger 共享主機  
**建立日期**: 2025-09-29  

## 🎯 部署目標

將 RentalRadar 專案成功部署到 Hostinger 共享主機環境，確保：
- ✅ 網站正常運作
- ✅ 地圖功能完整
- ✅ API 端點正常回應
- ✅ 排程任務自動執行
- ✅ 效能優化到位

## 🔧 技術環境需求

### Hostinger 環境限制
- **PHP 版本**: 8.4.5+ (Hostinger 已支援)
- **記憶體限制**: 256MB 單一 PHP 行程
- **檔案系統**: 僅允許在指定目錄內寫入
- **背景程序**: 不支援常駐 Node 服務
- **排程任務**: 支援 Cron Job，最小間隔 15 分鐘
- **資料庫**: 支援 MySQL 8.0+ (生產環境)

### 專案技術棧
- **後端**: Laravel 12.31.1
- **前端**: Inertia.js v2 + React 19.1.1
- **樣式**: Tailwind CSS v4.1.12
- **資料庫**: MySQL 8.0+ (生產) / SQLite (開發)
- **快取**: Redis (如果可用) 或檔案快取
- **排程**: Laravel Schedule + Hostinger Cron

## 🚀 部署步驟

### 📋 SSH 部署優勢
- ✅ **直接操作**: 在伺服器上直接執行命令
- ✅ **版本控制**: 使用 Git 進行程式碼管理
- ✅ **快速更新**: 使用 `git pull` 快速更新
- ✅ **環境一致**: 在實際環境中建置和測試
- ✅ **除錯方便**: 直接查看伺服器日誌和狀態

### 第一階段：SSH 連線準備

#### 1.1 準備 Git 倉庫
```bash
# 確保專案已推送到 Git 倉庫
git add .
git commit -m "準備部署到 Hostinger"
git push origin main

# 檢查遠端倉庫狀態
git remote -v
git status
```

#### 1.2 測試 SSH 連線
```bash
# 測試 SSH 連線 (在本地執行)
ssh your_username@your_hostinger_ip

# 如果首次連線，會要求確認主機金鑰
# 輸入 'yes' 確認
```

### 第二階段：Git Clone 部署

#### 2.1 SSH 登入並 Clone 專案
```bash
# SSH 登入 Hostinger
ssh your_username@your_hostinger_ip

# 進入 public_html 目錄
cd public_html

# 直接 clone 專案到當前目錄
git clone https://github.com/your-username/rentalradar.git .

# 檢查檔案結構
ls -la
# 應該看到：app/, config/, database/, resources/, routes/, storage/, vendor/, artisan, composer.json 等
```

#### 2.2 驗證 Clone 結果
```bash
# 檢查 Git 狀態
git status
git log --oneline -3

# 確認 Laravel 檔案存在
ls -la artisan composer.json
ls -la app/ config/ database/ resources/ routes/ storage/
```

### 第三階段：系統配置

#### 3.1 安裝依賴套件
```bash
# 在 Hostinger 主機上安裝 PHP 依賴
composer install --no-dev --optimize-autoloader --no-interaction

# 安裝前端依賴 (如果 Hostinger 支援 Node.js)
npm ci --silent

# 建置前端資源
npm run build
```

#### 3.2 設定環境變數
```bash
# 複製環境變數範本
cp .env.example .env

# 編輯環境變數
nano .env
```

**必要的環境變數設定**：
```env
APP_NAME=RentalRadar
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=error

# MySQL 資料庫配置 (生產環境)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=rentalradar_production
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password

# 測試資料庫配置 (可選)
DB_TEST_CONNECTION=mysql
DB_TEST_HOST=localhost
DB_TEST_PORT=3306
DB_TEST_DATABASE=rentalradar_test
DB_TEST_USERNAME=your_mysql_username
DB_TEST_PASSWORD=your_mysql_password

CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

#### 3.2 設定檔案權限
```bash
# 設定儲存目錄權限
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# 設定資料庫檔案權限
chmod 664 database/database.sqlite

# 設定應用程式檔案權限
chmod 644 artisan
chmod 644 composer.json
```

#### 3.3 執行 Laravel 優化
```bash
# 產生應用程式金鑰
php artisan key:generate

# 執行資料庫遷移
php artisan migrate --force

# 快取配置
php artisan config:cache

# 快取路由
php artisan route:cache

# 快取視圖
php artisan view:cache

# 清除舊快取
php artisan cache:clear
```

### 第四階段：排程設定

#### 4.1 設定 Hostinger Cron Jobs
在 Hostinger 控制台的 **Advanced** → **Cron Jobs** 中新增：

```bash
# 每分鐘執行 Laravel 排程
* * * * * cd /path/to/your/app && php artisan schedule:run >> /dev/null 2>&1
```

這是唯一需要的 Cron Job。`routes/console.php` 裡所有排程任務（政府資料下載、隊列處理、系統監控、資料保留清理）都由 Laravel 的排程器統一調度，`schedule:run` 每分鐘執行一次時會自動判斷哪些任務到期該跑。

其中「隊列處理」這項（`queue:work --stop-when-empty`，每分鐘執行）是共享主機環境的關鍵：Hostinger 不能常駐執行 `php artisan queue:work`，所以改用排程每分鐘啟動一次、處理完現有任務就結束的方式。管理員上傳的檔案（`ProcessFileUploadJob`）與其他隊列任務都是靠這個排程項目觸發實際處理，因此**若 Cron Job 沒設定好，上傳的檔案會卡在「處理中」狀態，永遠不會完成**。確認 `.env` 的 `QUEUE_CONNECTION=database`（本專案預設）即可，不需要額外安裝 Redis 或 Supervisor。

#### 4.2 配置資料保留排程
在 `.env` 檔案中新增：

```env
# 資料保留設定
DATA_RETENTION_PROPERTIES_DAYS=730
DATA_RETENTION_PREDICTIONS_DAYS=365
DATA_RETENTION_RECOMMENDATIONS_DAYS=365
DATA_RETENTION_RISK_ASSESSMENTS_DAYS=365
DATA_RETENTION_ANOMALIES_DAYS=180
DATA_RETENTION_FILE_UPLOADS_DAYS=90
DATA_RETENTION_SCHEDULE_EXECUTIONS_DAYS=30
DATA_RETENTION_CACHE_DAYS=7
DATA_RETENTION_SESSIONS_DAYS=1

# 檔案保留設定
FILE_RETENTION_GOVERNMENT_DATA_DAYS=7
FILE_RETENTION_UPLOADS_DAYS=30
FILE_RETENTION_LOGS_DAYS=7
FILE_RETENTION_ARCHIVES_DAYS=365

# 排程設定
DATA_RETENTION_DAILY_ENABLED=true
DATA_RETENTION_WEEKLY_ENABLED=true
DATA_RETENTION_MONTHLY_ENABLED=true
DATA_RETENTION_ARCHIVE_ENABLED=true
DATA_RETENTION_NOTIFICATIONS_ENABLED=true
```

## 📊 部署檢查清單

### 部署前檢查
- [ ] 確認 Hostinger 環境支援 PHP 8.0+
- [ ] 準備所有必要檔案
- [ ] 測試部署腳本
- [ ] 備份現有資料 (如果有)

### 部署中檢查
- [ ] 檔案上傳完成
- [ ] 環境變數設定正確
- [ ] 權限設定適當
- [ ] 資料庫遷移成功
- [ ] Laravel 優化執行完成

### 部署後驗證
- [ ] 網站正常載入 (訪問首頁)
- [ ] 地圖功能正常 (訪問 `/map`)
- [ ] API 端點回應 (測試 `/api/map/districts`)
- [ ] 排程任務執行 (檢查日誌)
- [ ] 效能監控正常
- [ ] 錯誤日誌無異常

## 🔍 功能測試

### 基本功能測試
```bash
# 測試首頁
curl -I https://yourdomain.com

# 測試地圖頁面
curl -I https://yourdomain.com/map

# 測試 API
curl https://yourdomain.com/api/map/districts
```

### 進階功能測試
```bash
# 測試排程執行
php artisan schedule:list
php artisan schedule:run

# 測試資料清理
php artisan data:cleanup --stats

# 測試系統監控
php artisan monitor:health
```

## 🚨 故障排除

### 常見問題

#### 1. 500 內部伺服器錯誤
**可能原因**：
- `.env` 檔案設定錯誤
- 檔案權限不正確
- 缺少必要的 PHP 擴展

**解決方案**：
```bash
# 檢查 Laravel 日誌
tail -f storage/logs/laravel.log

# 檢查檔案權限
ls -la storage/
ls -la bootstrap/cache/

# 重新設定權限
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

#### 2. 白畫面
**可能原因**：
- 缺少 `APP_KEY`
- 快取檔案損壞
- 資料庫連線問題

**解決方案**：
```bash
# 產生應用程式金鑰
php artisan key:generate

# 清除快取
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 重新快取
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 3. 地圖載入失敗
**可能原因**：
- JavaScript 檔案載入失敗
- API 端點無法存取
- 資料庫資料不完整

**解決方案**：
```bash
# 檢查前端資源
ls -la public/build/

# 重新建置前端
npm run build

# 檢查 API 端點
curl https://yourdomain.com/api/map/districts
```

#### 4. 排程任務不執行
**可能原因**：
- Cron Job 設定錯誤
- PHP 路徑不正確
- 檔案權限問題

**解決方案**：
```bash
# 檢查 Cron Job 設定
crontab -l

# 測試排程執行
php artisan schedule:run --verbose

# 檢查排程狀態
php artisan schedule:list
```

### 效能優化

#### 1. 啟用快取
```bash
# 啟用 Redis 快取 (如果可用)
CACHE_DRIVER=redis

# 或使用檔案快取
CACHE_DRIVER=file
```

#### 2. 優化資料庫
```bash
# 建立索引
php artisan migrate

# 清理過期資料
php artisan data:cleanup
```

#### 3. 監控系統資源
```bash
# 檢查記憶體使用
php artisan monitor:health

# 查看系統日誌
tail -f storage/logs/laravel.log
```

## 📈 監控與維護

### 日常監控
- 檢查網站回應時間
- 監控資料庫大小
- 查看錯誤日誌
- 確認排程任務執行

### 定期維護
- 清理過期資料
- 更新依賴套件
- 備份重要資料
- 優化資料庫查詢

### 效能監控
```bash
# 檢查系統健康狀態
php artisan monitor:health --send-alerts

# 執行資料清理
php artisan data:retention-schedule --frequency=daily

# 查看系統統計
php artisan data:cleanup --stats
```

## 🔒 安全考量

### 檔案權限
```bash
# 設定適當的檔案權限
chmod 644 .env
chmod 755 storage/
chmod 755 bootstrap/cache/
chmod 664 database/database.sqlite
```

### 環境變數保護
- 不要將 `.env` 檔案提交到版本控制
- 使用強密碼
- 定期更新應用程式金鑰

### 資料備份
```bash
# 備份資料庫
cp database/database.sqlite database/backup-$(date +%Y%m%d).sqlite

# 備份重要檔案
tar -czf backup-$(date +%Y%m%d).tar.gz storage/ database/
```

## 📞 技術支援

### 檢查清單
1. PHP 版本 >= 8.0
2. 所有檔案權限正確
3. `.env` 設定正確
4. 資料庫檔案存在且可寫入
5. 前端資源建置完成
6. 排程任務正常執行

### 日誌檔案位置
- Laravel 日誌: `storage/logs/laravel.log`
- 錯誤日誌: `storage/logs/error.log`
- 排程日誌: `storage/logs/schedule.log`

### 聯絡資訊
如有技術問題，請檢查：
1. 部署檢查清單
2. 故障排除指南
3. 系統日誌
4. 效能監控報告

## 🗄️ MySQL 資料庫設定

### 生產環境資料庫設定

#### 1. 在 Hostinger 控制台建立資料庫
1. 登入 Hostinger 控制台
2. 進入 **Databases** → **MySQL Databases**
3. 建立新資料庫：
   - **資料庫名稱**: `rentalradar_production`
   - **使用者名稱**: `rentalradar_user`
   - **密碼**: 設定強密碼
4. 將使用者指派給資料庫並給予完整權限

#### 2. 測試資料庫設定
1. 建立測試資料庫：
   - **資料庫名稱**: `rentalradar_test`
   - **使用者**: 可使用相同使用者或建立專用測試使用者
2. 設定測試環境變數

#### 3. Laravel 資料庫配置
```php
// config/database.php 中的 MySQL 配置
'mysql' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'rentalradar_production'),
    'username' => env('DB_USERNAME', 'rentalradar_user'),
    'password' => env('DB_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

#### 4. 執行資料庫遷移
```bash
# 執行生產環境遷移
php artisan migrate --force

# 如果需要在測試環境執行
php artisan migrate --database=mysql_test --force
```

#### 5. 資料庫連線測試
```bash
# 測試生產資料庫連線
php artisan tinker
>>> DB::connection()->getPdo();

# 測試測試資料庫連線
>>> DB::connection('mysql_test')->getPdo();
```

### 測試資料庫設定

#### 1. 建立測試專用配置
在 `config/database.php` 中新增測試資料庫連線：

```php
'connections' => [
    // ... 其他連線配置
    
    'mysql_test' => [
        'driver' => 'mysql',
        'host' => env('DB_TEST_HOST', '127.0.0.1'),
        'port' => env('DB_TEST_PORT', '3306'),
        'database' => env('DB_TEST_DATABASE', 'rentalradar_test'),
        'username' => env('DB_TEST_USERNAME', 'rentalradar_user'),
        'password' => env('DB_TEST_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ],
],
```

#### 2. 測試環境設定
```bash
# 設定測試環境變數
cp .env .env.testing

# 在 .env.testing 中設定測試資料庫
DB_CONNECTION=mysql_test
DB_DATABASE=rentalradar_test
```

#### 3. 執行測試
```bash
# 執行所有測試
php artisan test

# 執行特定測試
php artisan test --filter=DatabaseTest

# 使用測試資料庫執行測試
php artisan test --env=testing
```

### 資料庫備份與恢復

#### 1. 備份資料庫
```bash
# 備份生產資料庫
mysqldump -u rentalradar_user -p rentalradar_production > backup_$(date +%Y%m%d).sql

# 備份測試資料庫
mysqldump -u rentalradar_user -p rentalradar_test > test_backup_$(date +%Y%m%d).sql
```

#### 2. 恢復資料庫
```bash
# 恢復生產資料庫
mysql -u rentalradar_user -p rentalradar_production < backup_$(date +%Y%m%d).sql

# 恢復測試資料庫
mysql -u rentalradar_user -p rentalradar_test < test_backup_$(date +%Y%m%d).sql
```

### 效能優化建議

#### 1. MySQL 配置優化
```sql
-- 在 MySQL 中執行以下優化
SET GLOBAL innodb_buffer_pool_size = 128M;
SET GLOBAL query_cache_size = 32M;
SET GLOBAL max_connections = 100;
```

#### 2. Laravel 資料庫優化
```bash
# 建立索引
php artisan migrate

# 分析資料庫效能
php artisan db:show --table=properties

# 清理過期資料
php artisan data:cleanup
```

## 🔧 SSH 部署快速參考

### 完整部署命令序列
```bash
# 1. SSH 登入
ssh your_username@your_hostinger_ip

# 2. 進入專案目錄
cd public_html

# 3. Git Clone 專案
git clone https://github.com/your-username/rentalradar.git .

# 4. 安裝依賴
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --silent
npm run build

# 5. 設定環境
cp .env.example .env
nano .env  # 編輯環境變數

# 6. 設定權限
chmod -R 755 storage bootstrap/cache

# 7. 執行 Laravel 設定
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. 測試部署
php artisan --version
curl -I https://yourdomain.com
```

### 更新部署命令
```bash
# 1. SSH 登入
ssh your_username@your_hostinger_ip

# 2. 進入專案目錄
cd public_html

# 3. 拉取最新程式碼
git pull origin main

# 4. 更新依賴 (如有需要)
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --silent
npm run build

# 5. 執行資料庫遷移
php artisan migrate --force

# 6. 清除快取
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. 測試更新
curl -I https://yourdomain.com
```

### 常用 SSH 命令
```bash
# 查看檔案權限
ls -la

# 查看 Laravel 日誌
tail -f storage/logs/laravel.log

# 檢查 PHP 版本
php -v

# 檢查 Composer
composer --version

# 檢查 Node.js
node --version

# 檢查 Git 狀態
git status
git log --oneline -5

# 重啟 PHP-FPM (如果需要)
sudo systemctl restart php8.4-fpm
```

---

**部署完成後，請務必執行所有檢查項目，確保系統正常運作！**
