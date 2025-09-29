# RentalRadar 開發文件 📚

> **AI 驅動的租屋市場分析平台 - 開發指南**  
> *完整的技術文件與開發指南*

## 🏗️ 技術架構

### 後端
- **框架**: Laravel 12 + PHP 8.4
- **資料庫**: SQLite (開發) + MySQL (生產)
- **開發環境**: Laravel Herd
- **身份驗證**: Laravel Fortify
- **快取**: Redis 多層智能快取
- **效能**: 針對 50 萬筆以上房產記錄優化

### 前端
- **框架**: React + Inertia.js
- **地圖**: Leaflet.js + AI 優化
- **圖表**: Chart.js
- **樣式**: Tailwind CSS v4

### AI 功能
- **資料處理**: 智能清理與地理編碼
- **異常檢測**: 機器學習演算法
- **統計分析**: 深度資料探勘
- **地圖優化**: 效能調校與渲染優化
- **多 AI 協作**: Claude + Claude Code + Codex 團隊開發

### 效能優化
- **統計表格**: 預計算行政區與縣市統計 (247 個行政區 + 20 個縣市)
- **智能快取**: 多層快取策略 (熱/溫/冷區域)
- **事件驅動更新**: 資料變更時自動更新統計
- **資料庫索引**: 針對 50 萬筆以上記錄優化索引
- **查詢優化**: 聚合查詢效能提升 80% 以上

## 📊 資料來源

- **政府資料**: 不動產租賃實價登錄資料
- **更新頻率**: 每 10 天一次 (每月 1、11、21 日)
- **資料格式**: CSV 和 XML
- **下載連結**: [政府資料開放平台](https://data.moi.gov.tw/MoiOD/System/DownloadFile.aspx?DATA=F85D101E-1453-49B2-892D-36234CF9303D)

## 🚀 開發進度

### 第一階段：專案初始化 ✅
- [x] Laravel 12 + React 專案建置
- [x] 開發環境配置 (Herd)
- [x] Git 儲存庫初始化
- [x] README.md 建立

### 第二階段：AI 資料處理 ✅
- [x] AI 資料清理演算法
- [x] AI 異常檢測 (Codex 開發)
- [x] 多 AI 協作系統
- [x] Linear 專案管理整合
- [x] AI 地理編碼系統
- [x] 政府資料下載機制
- [x] AI 租屋價格預測模型 (DEV-27)
- [x] 推薦引擎系統
- [x] 異常檢測服務
- [x] 風險評估系統

### 第三階段：AI 地圖系統 ✅
- [x] Leaflet.js 整合
- [x] AI 優化地圖渲染
- [x] 熱力圖功能
- [x] 互動式標記
- [x] 聚合演算法優化 (DEV-25)

### 第四階段：AI 統計分析 ✅
- [x] 趨勢預測演算法
- [x] 市場分析功能
- [x] 推薦系統
- [x] 效能優化
- [x] 進階資料分析儀表板 (DEV-26)

### 第五階段：使用者回報系統 ✅
- [x] 使用者註冊驗證
- [x] 權重計算機制
- [x] 信譽評分系統
- [x] 資料品質控制
- [x] 效能監控系統 (DEV-22, DEV-23)

### 第六階段：權限管理系統 ✅
- [x] 管理員權限控制
- [x] 使用者管理系統
- [x] 檔案上傳權限
- [x] 排程管理
- [x] 效能監控儀表板

### 第七階段：資料處理效能優化 ✅
- [x] 統計表格實作 (district_statistics, city_statistics)
- [x] 智能快取系統
- [x] 事件驅動統計更新
- [x] 資料庫效能優化
- [x] 儀表板控制器優化

### 第八階段：政府平台申請 📋
- [ ] 活化申請
- [ ] 專案展示頁面
- [ ] 技術文件
- [ ] 展示影片

## 🎯 成功指標

### 技術指標
- [x] AI 資料處理準確率 > 95%
- [x] 地圖載入速度 < 2 秒
- [x] AI 統計分析回應 < 1 秒
- [x] 系統穩定性 > 99%
- [x] 查詢效能提升 80%+ (50 萬筆以上記錄)

### 展示指標
- [ ] 政府平台活化申請
- [ ] 作品集網站整合
- [ ] 完整技術文件
- [ ] 展示影片

## 🛠️ 開發環境設定

```bash
# 安裝相依套件
composer install
npm install

# 環境設定
cp .env.example .env
php artisan key:generate

# 資料庫設定
php artisan migrate

# 填入統計表格
php artisan statistics:populate

# 開發伺服器
php artisan serve
npm run dev
```

## 📥 資料下載與處理

### 下載政府租屋資料
```bash
# 下載並處理最新租屋資料
php artisan rental:process

# 下載並處理資料 (含清理)
php artisan rental:process --cleanup

# 下載並處理資料 (含驗證)
php artisan rental:process --validate

# 下載並處理資料 (含地理編碼)
php artisan rental:process --geocode

# 下載並處理資料 (含通知)
php artisan rental:process --notify

# 完整處理工作流程 (所有選項)
php artisan rental:process --cleanup --validate --geocode --notify
```

### 資料處理說明
- **資料來源**: 政府不動產租賃實價登錄資料
- **更新頻率**: 每 10 天一次 (每月 1、11、21 日)
- **資料格式**: 包含 CSV 和 XML 檔案的 ZIP 檔案
- **處理內容**:
  - 解析 CSV 檔案 (不動產租賃、建物不動產租賃)
  - 縣市對應 (透過 manifest.csv)
  - 時間格式轉換 (民國年轉西元年)
  - 面積單位轉換 (平方公尺轉坪)
  - 租金重新計算 (每坪租金)
  - 資料驗證與清理
  - 批次儲存至資料庫

### 資料庫結構
```sql
-- 主要欄位
city                    -- 縣市
district               -- 行政區
latitude               -- 緯度 (保留用於地理編碼)
longitude              -- 經度 (保留用於地理編碼)
is_geocoded            -- 是否已地理編碼
rental_type            -- 租賃類型
total_rent             -- 總租金
rent_per_ping          -- 每坪租金
rent_date              -- 租賃日期
building_type          -- 建物類型
area_ping              -- 面積 (坪)
building_age           -- 建物年齡
bedrooms               -- 房間數
living_rooms           -- 客廳數
bathrooms              -- 浴室數
has_elevator           -- 有電梯
has_management_organization -- 有管理組織
has_furniture          -- 有家具
```

### 效能優化功能
- **統計表格**: 預計算 247 個行政區和 20 個縣市的統計資料
- **智能快取**: 多層快取，具備熱/溫/冷區域策略
- **事件驅動更新**: 資料變更時自動更新統計
- **資料庫索引**: 針對高效能查詢優化的索引
- **查詢優化**: 聚合查詢效能提升 80% 以上

## 📝 開發日誌

### 2025-09-28 (重大效能優化)
- ✅ **資料處理效能優化** (DEV-33)
  - 實作統計表格 (district_statistics, city_statistics)
  - 新增智能多層快取系統
  - 事件驅動統計更新
  - 資料庫效能優化
  - 針對 50 萬筆以上記錄優化儀表板控制器
  - 查詢效能提升 80%+

### 2025-09-28 (權限管理系統)
- ✅ **完整權限管理系統** (DEV-32)
  - 管理員權限控制與使用者管理
  - 檔案上傳權限與處理
  - 排程管理系統
  - 效能監控儀表板
  - API 安全性，具備 CSRF 權杖保護

### 2025-09-27 (AI 功能核心服務)
- ✅ **AI 功能核心服務完成** (DEV-27)
  - 完整租屋價格預測模型訓練系統
  - 基於機器學習的租屋價格預測與市場趨勢分析
  - 個人化與熱門推薦系統
  - 價格與市場異常檢測
  - 投資風險評估
  - 時間序列分析：租屋趨勢分析、季節性模式檢測、未來預測
  - 完整 RESTful API 端點與控制器實作
  - 114 個測試全部通過，848 個斷言成功
  - 程式碼格式檢查通過，所有 IDE 錯誤已修復

### 2025-09-27 (資料庫結構重構與測試修復)
- ✅ **重大資料庫結構重構完成**
  - 重新設計 `properties` 表格結構，優化欄位命名與資料類型
  - 移除舊欄位並新增優化欄位
  - 建立新的遷移檔案以進行結構優化

- ✅ **全面測試修復完成**
  - 修復 **25 個失敗測試** → **0 個失敗**，全部 **118 個測試通過**
  - 更新 `PropertyFactory` 以符合新資料庫結構
  - 修復 `AIModelTrainingService` 資料處理邏輯
  - 更新 `MarketAnalysisService` 查詢與統計功能
  - 修復控制器與服務中的所有欄位引用
  - 解決 SQLite 檔案鎖定問題，切換至記憶體資料庫進行測試
  - 更新所有測試檔案以使用新的測試資料結構

## 🔄 開發工作流程

### 📋 提交前檢查清單
每次提交前，請檢查以下項目：

1. **Linear 狀態更新**
   - 檢查是否有已完成的 Linear Issues 需要狀態更新
   - 使用工具更新狀態：`node .ai-dev/core-tools/linear-issues.cjs update DEV-XX Done`
   - 常用狀態 ID：
     - 進行中：`a8c3ca26-39f0-4728-93ba-4130050d1abe`
     - 完成：`9fbe935a-aff3-4627-88a3-74353a55c221`

2. **程式碼檢查**
   - 執行 `npm run build` 確保建置成功
   - 執行 `php artisan test` 確保測試通過
   - 執行 `vendor/bin/pint --dirty` 確保程式碼格式正確

3. **文件更新**
   - 更新 `.ai-dev/PROGRESS.md` 記錄已完成的工作
   - 更新 `README.md` 開發日誌 (如需要)

### 🚀 標準提交流程
```bash
# 1. 檢查 Linear 狀態
node .ai-dev/core-tools/linear-issues.cjs list

# 2. 更新已完成 Issue 狀態
node .ai-dev/core-tools/linear-issues.cjs update DEV-XX Done

# 3. 檢查程式碼
npm run build
php artisan test
vendor/bin/pint --dirty

# 4. 提交變更
git add .
git commit -m "feat: describe completed functionality"
git push
```

## 📁 相關文件

- [API 文件](api/map-api-specification.md) - 地圖 API 規格說明
- [架構文件](architecture/phase-3-architecture.md) - 第三階段架構設計
- [部署文件](deployment/) - 部署相關指南
- [政府資料系統](government-data-system.md) - 政府資料整合說明

## 🤝 貢獻指南

本專案採用 AI 主導的開發模式，所有程式碼均由 AI 生成與優化。歡迎提供回饋與建議！

---

*此文件為 RentalRadar 專案的完整開發指南，包含所有技術細節與開發流程。*
