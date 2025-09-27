# RentalRadar 🏠

> **AI-Powered Rental Market Analytics Platform**  
> *Scan the rental market with AI precision*

## 🎯 專案概述

RentalRadar 是一個基於 AI 技術的租賃市場分析平台，整合政府開放資料，提供智慧化的租屋市場洞察。本專案旨在成為政府開放資料平台的首個活化應用案例，展現 AI 驅動開發能力和數據分析技能。

### 核心特色
- 🤖 **AI 驅動分析**: 智慧資料清理、異常值檢測、地理編碼
- 🗺️ **互動式地圖**: Leaflet.js + AI 優化的視覺化體驗
- 📊 **深度統計分析**: 趨勢預測和市場洞察
- 👥 **使用者回報系統**: 信譽評分和權重計算機制
- 🏆 **政府平台展示**: 活化政府開放資料的創新應用

## 🏗️ 技術架構

### 後端
- **框架**: Laravel 12 + PHP 8.4
- **資料庫**: SQLite (開發) + MySQL (生產)
- **開發環境**: Laravel Herd
- **認證**: Laravel Fortify

### 前端
- **框架**: React + Inertia.js
- **地圖**: Leaflet.js + AI 優化
- **圖表**: Chart.js
- **樣式**: Tailwind CSS v4

### AI 功能
- **資料處理**: 智慧清理和地理編碼
- **異常值檢測**: 機器學習演算法 (Codex 開發)
- **統計分析**: 深度數據挖掘
- **地圖優化**: 效能調校和渲染優化
- **多 AI 協作**: Claude + Claude Code + Codex 團隊開發

## 📊 資料來源

- **政府資料**: 不動產租賃實價登錄資料
- **更新頻率**: 每10日 (每月1、11、21日)
- **資料格式**: CSV 和 XML
- **下載連結**: [政府開放資料平台](https://data.moi.gov.tw/MoiOD/System/DownloadFile.aspx?DATA=F85D101E-1453-49B2-892D-36234CF9303D)

## 🚀 開發進度

### Phase 1: 專案初始化 ✅
- [x] Laravel 12 + React 專案建立
- [x] 開發環境設定 (Herd)
- [x] Git 倉庫初始化
- [x] README.md 建立

### Phase 2: AI 資料處理 ✅
- [x] AI 資料清理演算法
- [x] AI 異常值檢測 (Codex 開發)
- [x] 多 AI 協作系統建立
- [x] Linear 專案管理整合
- [x] AI 地理編碼系統
- [x] 政府資料下載機制
- [x] AI 租金預測模型 (DEV-27)
- [x] 推薦引擎系統
- [x] 異常檢測服務
- [x] 風險評估系統

### Phase 3: AI 地圖系統 ✅
- [x] Leaflet.js 整合
- [x] AI 優化地圖渲染
- [x] 熱力圖功能
- [x] 互動式標記
- [x] 聚合演算法優化 (DEV-25)

### Phase 4: AI 統計分析 ✅
- [x] 趨勢預測演算法
- [x] 市場分析功能
- [x] 推薦系統
- [x] 效能優化
- [x] 進階資料分析儀表板 (DEV-26)

### Phase 5: 使用者回報系統 ✅
- [x] 使用者註冊驗證
- [x] 權重計算機制
- [x] 信譽評分系統
- [x] 資料品質控制
- [x] 效能監控系統 (DEV-22, DEV-23)

### Phase 6: 政府平台申請 📋
- [ ] 活化應用申請
- [ ] 專案展示頁面
- [ ] 技術文檔
- [ ] Demo 影片

## 🎯 成功指標

### 技術指標
- [ ] AI 資料處理準確率 > 95%
- [ ] 地圖載入速度 < 2秒
- [ ] AI 統計分析響應 < 1秒
- [ ] 系統穩定性 > 99%

### 展示指標
- [ ] 政府平台活化應用上架
- [ ] 作品集網站整合
- [ ] 技術文檔完整
- [ ] Demo 展示影片

## 🛠️ 開發環境設定

```bash
# 安裝依賴
composer install
npm install

# 環境設定
cp .env.example .env
php artisan key:generate

# 資料庫設定
php artisan migrate

# 開發伺服器
php artisan serve
npm run dev
```

## 📝 開發日誌

### 2025-09-27
- ✅ 專案初始化完成
- ✅ Laravel 12 + React 環境建立
- ✅ Git 倉庫設定完成
- ✅ README.md 建立
- ✅ 使用者認證系統設定完成
  - 密碼確認頁面 (`/user/confirm-password`) 設定
  - 雙重驗證設定頁面 (`/settings/two-factor`) 設定
  - 個人資料設定頁面 (`/settings/profile`) 設定
  - 密碼設定頁面 (`/settings/password`) 設定
  - 外觀設定頁面 (`/settings/appearance`) 設定
  - 刪除帳號卡片組件設定
- ✅ 專業電子郵件驗證模板建立
  - 建立 `VerifyEmailMail` 類別
  - 設計美觀的 Markdown 郵件模板
  - 整合品牌元素和使用者體驗
- ✅ 應用程式設定優化
  - 時區設定為 `Asia/Taipei` (台北時間)
  - 語言設定為 `en` (英文)
  - 應用程式名稱設定為 `RentalRadar`
  - 郵件設定更新為專業模板
  - 清理多餘的語言檔案和配置
- ✅ 多 AI 協作系統建立
  - 建立 AI 團隊協作架構 (Claude + Claude Code + Codex)
  - 完成 Linear OAuth 整合和專案管理
  - 驗證多 AI 協作功能 (DEV-6, DEV-7)
  - 建立異常值檢測工具 (Codex 開發)
  - 建立 AI 角色提示系統和協作指南

### 2025-09-27 (最新更新)
- ✅ **AI 功能核心服務完成** (DEV-27)
  - 完整的租金預測模型訓練系統，支援特徵工程和標準化
  - 基於機器學習的租金價格預測和市場趨勢分析
  - 個人化和熱門推薦系統，基於用戶行為分析
  - 價格和市場異常檢測，支援統計和機器學習方法
  - 投資風險評估，包含市場、位置、財務風險分析
  - 時間序列分析：租金趨勢分析、季節性模式檢測、未來預測
  - 完整的 RESTful API 端點和控制器實作
  - 114 個測試全部通過，848 個斷言成功
  - 代碼格式檢查通過，所有 IDE 錯誤修復完成

### 2025-09-27 (資料庫結構重構與測試修復)
- ✅ **重大資料庫結構重構完成**
  - 重新設計 `properties` 表結構，優化欄位命名和資料類型
  - 移除舊欄位：`village`, `road`, `land_section`, `land_subsection`, `land_number`, `main_use`, `main_building_materials`, `construction_completion_year`, `total_floors`, `compartment_pattern`, `rent_per_month`, `rental_period`, `full_address`, `data_source`, `is_processed`, `processing_notes`
  - 新增欄位：`city`, `rental_type`, `rent_per_ping`, `area_ping`, `building_age`, `bedrooms`, `living_rooms`, `bathrooms`, `has_elevator`, `has_management_organization`, `has_furniture`
  - 建立新的遷移文件：`2025_09_27_200920_remove_unused_property_columns.php`

- ✅ **全面測試修復完成**
  - 修復 **25 個失敗測試** → **0 個失敗**，所有 **118 個測試通過**
  - 更新 `PropertyFactory` 以匹配新的資料庫結構
  - 修復 `AIModelTrainingService` 的資料處理邏輯
  - 更新 `MarketAnalysisService` 的查詢和統計功能
  - 修復所有控制器和服務中的欄位引用
  - 解決 SQLite 文件鎖定問題，改用內存資料庫進行測試
  - 更新所有測試文件中的測試數據結構

- ✅ **服務層架構優化**
  - 重構 `AIModelTrainingService` 的特徵提取和資料準備邏輯
  - 優化 `MarketAnalysisService` 的查詢效能和統計計算
  - 更新 `RecommendationEngine` 的推薦演算法
  - 修復所有 API 端點的驗證規則和資料處理

- ✅ **開發環境優化**
  - 解決 Cursor 編輯器的 SQLite 文件鎖定問題
  - 配置測試專用內存資料庫，提升測試執行速度
  - 優化資料庫配置，避免 WAL 模式導致的文件鎖定
  - 確保測試環境的穩定性和可靠性

### 2025-01-XX (前期更新)
- ✅ **前端 UI 重新設計完成**
  - 移除效能監控功能 (一般使用者不需要)
  - 重新設計首頁內容，專注於租屋市場分析
  - 新增智慧搜尋、市場趨勢等功能卡片
  - 優化統計資訊顯示 (平均租金、熱門區域)
  - 新增快速操作區塊 (進階篩選、智慧搜尋、價格預測、社群分享)
- ✅ **側邊欄縮進功能實現**
  - 實現專業的側邊欄縮進按鈕
  - 按鈕位於側邊欄和內容區域的邊線上
  - 支援圖標模式切換 (展開/收縮)
  - 地圖頁面可充分利用螢幕空間
- ✅ **效能監控系統整合**
  - 完成 DEV-20、DEV-22、DEV-23 開發任務
  - 整合 PerformanceMonitor 到各個控制器
  - 實現全域效能追蹤中間件
  - 建立效能監控儀表板 (管理員專用)
- ✅ **地圖頁面優化**
  - 更新瀏覽器標題為 "RentalRadar - 租屋地圖分析"
  - 實現側邊欄縮進功能，提供更大的地圖顯示空間
  - 優化頁面布局和用戶體驗

## 🔄 開發工作流程

### 📋 Commit 前檢查清單
在每次 commit 前，請檢查以下項目：

1. **Linear 狀態更新**
   - 檢查是否有完成的 Linear Issues 需要更新狀態
   - 使用工具更新狀態：`node .ai-dev/core-tools/linear-issues.cjs update DEV-XX Done`
   - 常用狀態 ID：
     - In Progress: `a8c3ca26-39f0-4728-93ba-4130050d1abe`
     - Done: `9fbe935a-aff3-4627-88a3-74353a55c221`

2. **程式碼檢查**
   - 執行 `npm run build` 確保建置成功
   - 執行 `php artisan test` 確保測試通過
   - 執行 `vendor/bin/pint --dirty` 確保程式碼格式正確

3. **文檔更新**
   - 更新 `.ai-dev/PROGRESS.md` 記錄完成的工作
   - 更新 `README.md` 開發日誌（如需要）

### 🚀 標準 Commit 流程
```bash
# 1. 檢查 Linear 狀態
node .ai-dev/core-tools/linear-issues.cjs list

# 2. 更新完成的 Issue 狀態
node .ai-dev/core-tools/linear-issues.cjs update DEV-XX Done

# 3. 檢查程式碼
npm run build
php artisan test
vendor/bin/pint --dirty

# 4. 提交變更
git add .
git commit -m "feat: 描述完成的功能"
git push
```

## 🤝 貢獻指南

本專案採用 AI 主導開發模式，所有程式碼由 AI 生成和優化。歡迎提供回饋和建議！

## 📄 授權

MIT License

---

**🚀 專案代號**: RentalRadar  
**👨‍💻 開發模式**: 全程 AI 主導  
**📅 預計完成**: 9週  
**🎯 最終目標**: 政府平台活化應用展示 + 作品集亮點

*"讓每個租屋族都能用數據找到好房子！"* 🏠✨
