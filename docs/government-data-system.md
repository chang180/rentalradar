# 政府資料下載系統文檔

## 📋 系統概述

政府資料下載系統是一個完整的自動化資料處理流程，用於下載、解析、驗證和儲存政府開放資料平台的租賃實價登錄資料。

## 🏗️ 系統架構

### 核心服務
- **GovernmentDataDownloadService**: 負責下載政府資料
- **DataParserService**: 負責解析 CSV/XML 資料
- **DataValidationService**: 負責資料驗證和品質檢查
- **GeocodingService**: 負責地理編碼處理

### 命令工具
- **DownloadGovernmentData**: 下載政府資料
- **ProcessRentalData**: 完整資料處理流程
- **DataStatusCommand**: 檢查系統狀態
- **TestGovernmentDataSystem**: 測試系統功能
- **GovernmentDataMaintenance**: 系統維護

## 🚀 使用方法

### 基本下載
```bash
# 下載 CSV 格式資料
php artisan government:download --format=csv

# 下載 XML 格式資料
php artisan government:download --format=xml
```

### 完整處理流程
```bash
# 下載、解析、驗證、儲存
php artisan rental:process --format=csv --validate --geocode --notify
```

### 系統維護
```bash
# 檢查系統狀態
php artisan government:maintenance --status

# 清理舊檔案
php artisan government:maintenance --cleanup

# 驗證資料品質
php artisan government:maintenance --validate

# 執行地理編碼
php artisan government:maintenance --geocode

# 完整維護
php artisan government:maintenance --full
```

### 系統測試
```bash
# 基本測試
php artisan government:test

# 完整測試
php artisan government:test --full
```

## 📊 功能特色

### 1. 自動下載機制
- 支援 CSV 和 XML 格式
- 自動重試機制 (最多 3 次)
- 錯誤處理和日誌記錄
- 檔案大小和時間追蹤

### 2. 智慧資料解析
- 自動識別資料格式
- 支援多種日期格式
- 價格和面積資料清理
- 房間數解析 (3房2廳1衛)

### 3. 資料驗證系統
- 必要欄位檢查
- 價格合理性驗證
- 地址格式檢查
- 資料完整性評估

### 4. 品質監控
- 資料品質評分
- 完整性、準確性、一致性檢查
- 統計分析報告
- 建議改進措施

### 5. 地理編碼整合
- 自動地理編碼
- 地址格式優化
- 批量處理
- 錯誤重試機制

## 🔧 配置設定

### 環境變數
```env
# 政府資料下載設定
GOVERNMENT_DATA_BASE_URL=https://data.moi.gov.tw/MoiOD/System/DownloadFile.aspx
GOVERNMENT_DATA_ID=F85D101E-1453-49B2-892D-36234CF9303D

# 地理編碼設定
GEOCODING_SERVICE_URL=https://nominatim.openstreetmap.org/search
GEOCODING_USER_AGENT=RentalRadar/1.0 (taiwan.rental.radar@gmail.com)
```

### 排程設定
```php
// app/Console/Kernel.php
$schedule->command('government:download --format=csv --parse --save')
    ->monthlyOn(1, '02:00')
    ->monthlyOn(11, '02:00')
    ->monthlyOn(21, '02:00');
```

## 📈 效能指標

### 下載效能
- 下載速度: < 30秒
- 檔案大小: 通常 500KB - 2MB
- 重試機制: 最多 3 次
- 成功率: > 95%

### 解析效能
- 處理速度: < 60秒 (1000筆資料)
- 記憶體使用: < 100MB
- 錯誤率: < 5%
- 支援格式: CSV, XML

### 驗證效能
- 驗證速度: < 10秒 (1000筆資料)
- 準確率: > 85%
- 完整性: > 90%
- 一致性: > 80%

## 🛠️ 故障排除

### 常見問題

#### 1. 下載失敗
```bash
# 檢查網路連線
curl -I https://data.moi.gov.tw/MoiOD/System/DownloadFile.aspx

# 檢查系統狀態
php artisan government:maintenance --status
```

#### 2. 解析錯誤
```bash
# 檢查檔案格式
php artisan government:test --full

# 查看詳細錯誤
tail -f storage/logs/laravel.log
```

#### 3. 地理編碼失敗
```bash
# 檢查 OpenStreetMap API
curl "https://nominatim.openstreetmap.org/search?q=台北市&format=json&limit=1"

# 執行地理編碼
php artisan properties:geocode --limit=10
```

### 日誌檢查
```bash
# 查看系統日誌
tail -f storage/logs/laravel.log

# 查看下載日誌
grep "政府資料" storage/logs/laravel.log
```

## 🔒 安全性

### 資料保護
- 敏感資料加密儲存
- 存取權限控制
- 日誌記錄追蹤
- 定期備份機制

### 錯誤處理
- 異常捕獲和記錄
- 重試機制
- 降級處理
- 監控告警

## 📚 技術參考

### 政府資料來源
- [政府開放資料平台](https://data.gov.tw/dataset/25118)
- [直接下載連結](https://data.moi.gov.tw/MoiOD/System/DownloadFile.aspx?DATA=F85D101E-1453-49B2-892D-36234CF9303D)

### Laravel 功能
- [Laravel HTTP Client](https://laravel.com/docs/12.x/http-client)
- [Laravel Queues](https://laravel.com/docs/12.x/queues)
- [Laravel Scheduling](https://laravel.com/docs/12.x/scheduling)
- [Laravel Notifications](https://laravel.com/docs/12.x/notifications)

### 資料處理
- [CSV 處理](https://laravel.com/docs/12.x/collections)
- [XML 處理](https://www.php.net/manual/en/simplexml.examples.php)
- [資料驗證](https://laravel.com/docs/12.x/validation)

## 🎯 未來改進

### 短期目標
- 提升解析準確率
- 優化地理編碼成功率
- 增加更多資料來源
- 改善錯誤處理

### 長期目標
- 機器學習模型整合
- 即時資料更新
- 多語言支援
- 雲端部署優化

## 📞 支援

如有問題或建議，請聯繫開發團隊或查看系統日誌。
