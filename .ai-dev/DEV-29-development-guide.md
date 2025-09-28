# DEV-29: 人員權限管理系統開發指南

## 📋 任務概述
建立簡單的權限管理系統，控制重要功能的存取權限，包含資料上傳匯入、管理員權限等功能。

## 🎯 核心功能需求

### 1. 管理員權限控制
- 建立管理員專用功能的存取權限控制
- 區分一般使用者和管理員的功能存取權限
- 實作權限檢查中介軟體
- 效能監控頁面加入管理員權限驗證

### 2. 自行上傳並匯入資料權限
- 建立資料上傳功能的權限控制（僅管理員）
- 實作檔案上傳安全檢查機制
- 建立政府資料檔案格式驗證
- 實作資料匯入和驗證機制
- 重新加入 serial_number 欄位進行資料驗證

### 3. 管理員介面設計
- 建立管理員專用介面
- 實作管理員身份切換功能
- 建立檔案上傳介面
- 建立排程管理介面

### 4. 簡單權限管理
- 建立兩層級權限系統（一般使用者/管理員）
- 實作管理員權限檢查
- 建立權限中介軟體
- 建立使用者管理功能（提升為管理員、刪除使用者）

### 5. 基本排程管理
- 建立排程設定管理功能（修改時間和開關）
- 實作手動觸發排程功能
- 建立排程執行記錄
- 支援未來其他排程任務擴展

### 6. 前端介面設計
- 在現有 dashboard 中根據 `is_admin` 權限控制側邊欄功能
- 實作檔案上傳介面（僅管理員可見）
- 建立排程管理介面（僅管理員可見）
- 建立使用者管理介面（僅管理員可見）
- 實作權限狀態顯示

## 🏗️ 技術架構設計

### 後端架構
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AdminController.php
│   │   ├── FileUploadController.php
│   │   ├── ScheduleController.php
│   │   └── PermissionController.php
│   ├── Middleware/
│   │   ├── CheckAdmin.php
│   │   └── CheckUploadPermission.php
│   └── Requests/
│       ├── FileUploadRequest.php
│       ├── ScheduleRequest.php
│       └── AdminRequest.php
├── Models/
│   ├── User.php (新增 role_type 欄位)
│   ├── FileUpload.php
│   ├── ScheduleSetting.php
│   └── ScheduleExecution.php
├── Services/
│   ├── PermissionService.php
│   ├── FileUploadService.php
│   ├── ScheduleService.php
│   └── PermissionAuditService.php
└── Console/
    └── Commands/
        └── DataDownloadCommand.php
```

### 資料庫設計
```sql
-- 在現有的 users 表中新增權限欄位
ALTER TABLE users ADD COLUMN is_admin BOOLEAN DEFAULT FALSE COMMENT '是否為管理員';

-- 在現有的 properties 表中新增 serial_number 欄位
ALTER TABLE properties ADD COLUMN serial_number VARCHAR(255) UNIQUE COMMENT '政府資料序號，用於資料驗證和去重';

-- 檔案上傳記錄表
CREATE TABLE file_uploads (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    user_id BIGINT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    upload_path VARCHAR(500) NOT NULL,
    upload_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    processing_result TEXT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 排程設定表
CREATE TABLE schedule_settings (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    task_name VARCHAR(255) NOT NULL UNIQUE,
    frequency VARCHAR(50) NOT NULL DEFAULT 'monthly',
    execution_days JSON NOT NULL DEFAULT '[5, 15, 25]',
    execution_time TIME NOT NULL DEFAULT '02:00',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 排程執行記錄表
CREATE TABLE schedule_executions (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    task_name VARCHAR(255) NOT NULL,
    scheduled_at TIMESTAMP NOT NULL,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    result TEXT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 前端架構
```
resources/js/
├── components/
│   ├── admin/                    # 管理員專用組件
│   │   ├── FileUpload.tsx        # 檔案上傳組件
│   │   ├── ScheduleManagement.tsx # 排程管理組件
│   │   ├── UserManagement.tsx     # 使用者管理組件
│   │   └── AdminStatus.tsx       # 管理員狀態組件
│   └── app-sidebar.tsx           # 現有側邊欄（需加入權限控制）
├── pages/
│   └── dashboard.tsx             # 現有 dashboard（需加入管理員功能）
├── hooks/
│   ├── useAdmin.ts               # 管理員權限檢查 hook
│   ├── useFileUpload.ts          # 檔案上傳 hook
│   ├── useSchedule.ts            # 排程管理 hook
│   └── useUserManagement.ts      # 使用者管理 hook
```

## 🔧 實作步驟

### 階段 1: 權限管理基礎架構 (1-2 天)

#### 1.1 建立資料庫遷移
```bash
php artisan make:migration add_is_admin_to_users_table
php artisan make:migration add_serial_number_to_properties_table
php artisan make:migration create_file_uploads_table
php artisan make:migration create_schedule_settings_table
php artisan make:migration create_schedule_executions_table
```

#### 1.2 建立模型
```bash
php artisan make:model FileUpload
php artisan make:model ScheduleSetting
php artisan make:model ScheduleExecution
# User 模型已存在，需要更新以支援 is_admin 欄位
```

#### 1.3 建立服務類別
```bash
php artisan make:class PermissionService
php artisan make:class FileUploadService
php artisan make:class ScheduleService
php artisan make:class UserManagementService
```

#### 1.4 建立中介軟體
```bash
php artisan make:middleware CheckAdmin
```

#### 1.5 建立 Artisan 命令
```bash
php artisan make:command DataDownloadCommand
php artisan make:command ProcessUploadedData
php artisan make:command ManageUsers
```

### 階段 2: 簡單權限系統 (1 天)

#### 2.1 實作權限檢查邏輯
- 建立管理員權限檢查（is_admin 欄位）
- 效能監控頁面加入管理員權限驗證
- 建立權限快取機制

#### 2.2 實作使用者管理
- 建立管理員身份切換功能
- 實作管理員權限分配
- 建立使用者列表功能
- 實作提升使用者為管理員功能
- 實作刪除使用者功能
- 在現有 dashboard 中根據 `is_admin` 控制側邊欄功能

#### 2.3 實作權限 API
- 建立權限檢查 API
- 實作管理員功能 API
- 建立權限狀態 API
- 建立使用者管理 API（列表、提升、刪除）

### 階段 3: 資料上傳權限控制 (2 天)

#### 3.1 建立檔案上傳功能
- 實作政府資料檔案上傳 API
- 建立檔案安全檢查
- 實作政府資料格式驗證
- **重要**: 實作 serial_number 重複檢測機制
- 建立檔案大小和類型限制
- 在現有 dashboard 中整合檔案上傳介面

#### 3.2 建立資料匯入功能
- 實作政府資料解析邏輯（重用現有邏輯）
- 建立 serial_number 資料驗證機制
- **重要**: 實作資料衝突處理策略
- **重要**: 建立多期資料匯入測試機制
- 實作重複資料檢查和處理
- 建立匯入進度追蹤

#### 3.3 建立上傳安全機制
- 實作惡意檔案檢測
- 建立檔案大小限制（如 100MB）
- 實作上傳頻率限制
- 建立檔案格式白名單（.zip, .csv）

### 階段 4: 排程管理系統 (1-2 天)

#### 4.1 建立排程設定功能
- 實作排程設定管理（修改時間和開關）
- 建立預設排程（每月 5, 15, 25 日）
- 實作排程啟用/停用功能
- 支援未來其他排程任務擴展
- 在現有 dashboard 中整合排程管理介面

#### 4.2 建立排程監控功能
- 實作排程狀態監控
- 建立排程執行歷史
- 實作手動觸發排程

#### 4.3 建立排程執行邏輯
- 實作 Laravel Schedule 整合
- 建立排程執行記錄
- 實作排程錯誤處理

### 階段 5: 權限審計系統 (1-2 天)

#### 5.1 建立審計日誌
- 實作權限操作記錄
- 建立權限變更追蹤
- 實作權限違規警報

#### 5.2 建立審計報告
- 實作權限使用統計
- 建立權限分析報告
- 實作權限趨勢分析

### 階段 5: 測試和優化 (1-2 天)

#### 5.1 單元測試
- 權限服務測試
- 角色管理測試
- 權限檢查測試

#### 5.2 整合測試
- API 端點測試
- 權限流程測試
- 安全測試

#### 5.3 效能優化
- 權限快取優化
- 查詢效能優化
- 記憶體使用優化

## 🔒 安全考量

### 權限驗證
- 實作 JWT Token 權限驗證
- 建立權限快取機制
- 實作權限過期檢查
- 建立權限刷新機制

### 資料安全
- 實作檔案上傳安全檢查
- 建立惡意檔案檢測機制
- 實作資料匯入權限驗證
- 建立敏感資料存取控制

### 審計追蹤
- 建立完整的權限操作日誌
- 實作權限變更通知機制
- 建立權限違規警報系統
- 實作權限使用分析報告

## 📊 API 端點設計

### 管理員功能 API
```
GET    /api/admin/users              # 取得使用者列表
PUT    /api/admin/users/{id}/role    # 更新使用者角色
GET    /api/admin/dashboard          # 取得管理員儀表板
```

### 檔案上傳 API
```
POST   /api/upload/government-data   # 上傳政府資料檔案
POST   /api/upload/process           # 處理上傳的檔案
GET    /api/upload/history           # 取得上傳歷史
GET    /api/upload/{id}/status       # 取得處理狀態
DELETE /api/upload/{id}              # 刪除上傳檔案
```

### 排程管理 API
```
GET    /api/admin/schedules          # 取得排程設定
PUT    /api/admin/schedules/{id}     # 更新排程設定
POST   /api/admin/schedules/{id}/execute # 手動執行排程
GET    /api/admin/schedules/executions # 取得執行歷史
GET    /api/admin/schedules/status   # 取得排程狀態
```

### 權限檢查 API
```
GET    /api/permissions/check        # 檢查使用者權限
GET    /api/permissions/status       # 取得權限狀態
```

### 權限審計 API
```
GET    /api/permission-logs          # 取得權限操作日誌
GET    /api/permission-stats         # 取得權限使用統計
```

## 🧪 測試策略

### 單元測試
- 權限服務測試
- 角色管理測試
- 權限檢查中介軟體測試
- 權限審計服務測試

### 整合測試
- 權限 API 端點測試
- 權限檢查流程測試
- 權限審計功能測試
- 權限安全測試

### 效能測試
- 權限檢查效能測試
- 權限快取效能測試
- 大量使用者權限測試
- 權限查詢效能測試

## 📈 預期成果

### 功能成果
- 完整的權限管理系統
- 安全的資料上傳機制
- 管理員權限控制系統
- 使用者角色分級管理
- 權限審計和日誌系統

### 技術成果
- 模組化的權限管理架構
- 高效的權限檢查機制
- 完整的權限審計系統
- 安全的檔案上傳機制
- 可擴展的角色權限系統

### 業務成果
- 提升系統安全性
- 改善使用者體驗
- 增強管理效率
- 降低安全風險
- 提升系統可維護性

## 🚀 開發時程
- **階段 1**: 權限管理基礎架構 (1 天)
- **階段 2**: 簡單權限系統 (1 天)
- **階段 3**: 資料上傳權限控制 (2 天)
- **階段 4**: 排程管理系統 (1-2 天)
- **階段 5**: 測試和優化 (1 天)

**總預估時間**: 3-5 天

## 🔗 相依性
- Laravel Fortify（已安裝，現有驗證系統）
- 檔案上傳安全檢查
- Redis 快取（已設定）
- Hostinger Cron Job 設定

## 🕒 Hostinger 排程執行設定

### 1. **Cron Job 設定**
在 Hostinger 控制面板中設定：
```bash
* * * * * php /path/to/artisan schedule:run
```

### 2. **Laravel Schedule 設定**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // 根據資料庫設定動態執行
    $schedule->command('data:download')
        ->when(function () {
            return ScheduleSetting::isTimeToExecute('data_download');
        });
}
```

### 3. **預設排程設定**
- **執行日期**: 每月 5, 15, 25 日
- **執行時間**: 凌晨 2:00
- **任務名稱**: data_download
- **與政府上傳日錯開**: 避免資料衝突

## 📊 自行上傳資料驗證機制

### 1. **重用現有邏輯**
- ❌ `serial_number` 欄位需要重新加入 `properties` 表
- ✅ **重用現有解析邏輯**：`DataParserService` 已完整支援政府資料格式
- ✅ **重用現有驗證機制**：檔案格式驗證與排程下載完全相同
- ✅ 需要更新 Property 模型加入 `serial_number` 到 `fillable` 陣列
- ✅ 現有的重複資料檢查邏輯可以重用

### 2. **資料驗證流程**
```
上傳檔案 → 重用現有解析邏輯 → serial_number 檢查 → 重複資料處理 → 匯入資料庫
```

### 3. **重用現有機制**
- ✅ **檔案格式驗證**：直接使用 `DataParserService` 的驗證邏輯
- ✅ **資料解析**：重用 `normalizeRentalRecord` 方法
- ✅ **serial_number 檢查**：檢查政府資料中的序號是否已存在
- ✅ **重複資料處理**：避免重複匯入相同資料，支援更新現有資料
- 記錄資料來源和匯入時間

### 4. **重複資料處理策略**
- **新增**: serial_number 不存在時直接新增
- **更新**: serial_number 存在時更新現有記錄
- **跳過**: 相同 serial_number 且資料無變化時跳過
- **衝突處理**: serial_number 相同但資料不同時的處理策略
- **重複檢測**: 檢測 serial_number 本身的重複問題
- **記錄**: 所有操作都記錄到上傳歷史

### 5. **serial_number 重複驗證機制**

#### 5.1 **政府資料重複檢測**
- **檔案內重複**: 檢測同一檔案中是否有重複的 serial_number
- **跨檔案重複**: 檢測不同檔案間是否有重複的 serial_number
- **資料庫重複**: 檢測與現有資料庫中 serial_number 的重複

#### 5.2 **資料衝突處理策略**
- **相同 serial_number，相同資料**: 跳過匯入
- **相同 serial_number，不同資料**: 提供衝突解決選項
  - 保留現有資料
  - 覆蓋為新資料
  - 手動比較後決定
- **serial_number 為空或無效**: 生成唯一識別碼

#### 5.3 **過期資料匯入測試**
- **多期資料匯入**: 測試不同時期的政府資料
- **重複檢測**: 驗證 serial_number 重複處理機制
- **資料一致性**: 確保資料更新的正確性
- **效能測試**: 大量資料匯入的效能驗證

### 6. **檔案格式支援**
- **ZIP 檔案**: 政府提供的完整資料包
- **CSV 檔案**: 單一縣市資料檔案
- **格式驗證**: 檢查檔案結構和必要欄位
- **編碼處理**: 支援 Big-5 到 UTF-8 轉換

## 📝 注意事項
- 確保所有權限檢查都有適當的錯誤處理
- 實作權限快取以提高效能
- 確保檔案上傳的安全性
- **重要**: 實作完整的 serial_number 重複檢測機制
- **重要**: 建立資料衝突解決策略
- **重要**: 進行多期過期資料匯入測試
- **重要**: 使用者管理功能需要適當的權限驗證
- **重要**: 刪除使用者前需要確認操作
- 確保資料一致性和完整性
