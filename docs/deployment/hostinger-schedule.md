# Hostinger 排程設定指南

## 概述

本專案已針對 Hostinger 的每分鐘排程執行系統進行優化，所有排程任務都會在每分鐘檢查時自動執行。

## 排程任務清單

### 1. 系統監控與維護

| 任務 | 頻率 | 時間 | 說明 |
|------|------|------|------|
| `monitor:health --send-alerts` | 每 5 分鐘 | - | 監控系統健康狀態 |
| `monitor:health --auto-repair` | 每 30 分鐘 | - | 自動修復系統問題 |
| `data:update --geocode --limit=1000` | 每日 | 02:00 | 每日資料更新 |
| `data:update --force --geocode --limit=5000` | 每週 | 週日 03:00 | 每週完整資料更新 |
| `data:update --force --geocode --limit=10000` | 每月 | 1號 04:00 | 每月深度維護 |

### 2. 資料保留與清理

| 任務 | 頻率 | 時間 | 說明 |
|------|------|------|------|
| `data:retention-schedule --frequency=hourly` | 每小時 | - | 每小時清理緊急資料 |
| `data:retention-schedule --frequency=6hourly` | 每 6 小時 | - | 清理臨時資料 |
| `data:retention-schedule --frequency=daily` | 每日 | 01:00 | 每日清理快取和會話 |
| `data:retention-schedule --frequency=weekly` | 每週 | 週日 01:30 | 每週清理檔案和排程記錄 |
| `data:retention-schedule --frequency=monthly` | 每月 | 1號 02:00 | 每月完整資料清理 |
| `data:cleanup --stats` | 每週 | 週三 03:00 | 顯示資料保留統計 |

## Hostinger 設定步驟

### 1. 登入 Hostinger 控制台

1. 登入您的 Hostinger 帳戶
2. 進入 **Advanced** → **Cron Jobs**

### 2. 設定排程任務

在 Hostinger 的 Cron Jobs 設定中，新增以下排程：

```bash
# 每分鐘執行 Laravel 排程
* * * * * cd /public_html && php artisan schedule:run >> /dev/null 2>&1
```

### 3. 環境變數設定

確保在 Hostinger 的環境變數中設定：

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

## 排程執行原理

### Laravel 排程系統

Laravel 的排程系統會：

1. **每分鐘檢查**：Hostinger 每分鐘執行 `php artisan schedule:run`
2. **時間匹配**：Laravel 檢查當前時間是否匹配排程任務的時間
3. **執行任務**：如果時間匹配，執行對應的任務
4. **防止重疊**：使用 `withoutOverlapping()` 防止任務重疊執行
5. **單一伺服器**：使用 `onOneServer()` 確保在多伺服器環境中只執行一次

### 任務執行流程

```
每分鐘觸發
    ↓
檢查排程時間
    ↓
匹配的任務
    ↓
檢查是否正在執行
    ↓
執行任務
    ↓
記錄執行結果
```

## 監控與除錯

### 1. 檢查排程狀態

```bash
# 查看排程列表
php artisan schedule:list

# 測試排程執行
php artisan schedule:run

# 查看排程狀態
php artisan schedule:work
```

### 2. 手動執行任務

```bash
# 手動執行資料清理
php artisan data:cleanup --stats

# 模擬執行清理
php artisan data:cleanup --dry-run

# 強制執行清理
php artisan data:cleanup --force

# 執行特定頻率的清理
php artisan data:retention-schedule --frequency=daily
php artisan data:retention-schedule --frequency=weekly
php artisan data:retention-schedule --frequency=monthly
```

### 3. 查看執行日誌

```bash
# 查看 Laravel 日誌
tail -f storage/logs/laravel.log

# 查看排程執行記錄
grep "schedule" storage/logs/laravel.log
```

## 效能優化

### 1. 排程優化

- 使用 `withoutOverlapping()` 防止任務重疊
- 使用 `onOneServer()` 確保單一執行
- 使用 `runInBackground()` 非同步執行

### 2. 記憶體管理

- 定期清理快取資料
- 清理過期會話
- 歸檔重要資料

### 3. 資料庫優化

- 分批處理大量資料
- 使用索引優化查詢
- 定期清理過期資料

## 故障排除

### 常見問題

1. **排程不執行**
   - 檢查 Hostinger Cron Jobs 設定
   - 確認 PHP 路徑正確
   - 檢查檔案權限

2. **任務重疊執行**
   - 檢查 `withoutOverlapping()` 設定
   - 查看任務執行時間

3. **記憶體不足**
   - 調整 PHP 記憶體限制
   - 優化資料處理邏輯

### 除錯命令

```bash
# 檢查 PHP 版本
php -v

# 檢查 Laravel 環境
php artisan env

# 檢查排程狀態
php artisan schedule:list

# 測試排程執行
php artisan schedule:run --verbose
```

## 安全考量

### 1. 資料備份

- 重要資料先歸檔再刪除
- 定期備份資料庫
- 保留歸檔檔案

### 2. 權限控制

- 限制排程執行權限
- 使用安全的檔案路徑
- 保護敏感資料

### 3. 監控告警

- 設定執行失敗告警
- 監控系統資源使用
- 記錄所有操作日誌

## 維護建議

### 1. 定期檢查

- 每週檢查排程執行狀態
- 監控資料庫大小
- 檢查系統效能

### 2. 調整設定

- 根據使用情況調整保留期限
- 優化排程執行時間
- 調整清理頻率

### 3. 備份策略

- 定期備份重要資料
- 測試資料恢復流程
- 保留多個備份版本
