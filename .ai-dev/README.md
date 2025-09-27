# 🤖 AI 開發工具目錄

## 📁 目錄結構

### **core-tools/** - 核心工具
- `linear-oauth-integration.cjs` - OAuth 認證和基本 API 操作
- `README.md` - 核心工具說明

### **ai-system/** - AI 協作系統
- `ai-team-config.json` - AI 團隊設定
- `AI-TEAM-GUIDE.md` - AI 團隊協作指南
- `CLAUDE.md` - 專案開發指南
- `phase-3-coordination.md` - Phase 3 協調指南
- `ai-roles/` - AI 角色提示檔案
  - `claude-code-prompt.md` - Claude Code 角色提示
  - `codex-prompt.md` - Codex 角色提示

### **開發指南檔案**
- `PROGRESS.md` - 開發進度追蹤
- `current_system_assessment.md` - 系統狀態評估
- `DEV-24-execution-guide.md` - DEV-24 執行指南
- `DEV-25-development-guide.md` - DEV-25 開發指南
- `DEV-26-development-guide.md` - DEV-26 開發指南
- `DEV-27-development-guide.md` - DEV-27 開發指南
- `DEV-28-development-guide.md` - DEV-28 開發指南

### **archive/** - 封存檔案
- 過時的腳本和文檔
- 一次性使用的工具
- 舊版本的管理工具
- `analyze_rental_data.php` - 舊版資料分析腳本（已過時）

## 🚀 使用方式

### **資料處理**
```bash
# 下載並處理政府租賃資料
php artisan rental:process

# 包含清理舊檔案
php artisan rental:process --cleanup

# 完整處理流程
php artisan rental:process --cleanup --validate --geocode --notify
```

### **開發進度追蹤**
- 查看 `PROGRESS.md` 了解最新開發進度
- 查看 `current_system_assessment.md` 了解系統狀態
- 參考各 DEV-XX 開發指南進行特定功能開發

### **AI 協作**
1. 參考 `ai-system/AI-TEAM-GUIDE.md` 了解協作流程
2. 使用 `ai-system/ai-roles/` 中的角色提示
3. 透過 Linear Issues 管理任務

## 🔧 維護說明

- **core-tools/**: 經常使用的核心工具
- **ai-system/**: AI 協作相關設定和指南
- **開發指南檔案**: 各階段開發指南和進度追蹤
- **archive/**: 過時檔案，可定期清理

## 📝 更新日誌

- **2025-09-27 22:30**: 完成地圖系統重構和聚合資料整合
  - 實現地理聚合系統 (GeoAggregationService)
  - 建立台灣地理中心點服務 (TaiwanGeoCenterService)
  - 重構地圖頁面支援全台租屋市場統計
  - 實現縣市和鄉鎮市區兩層選擇器
  - 修復 MapController API 端點問題
  - 更新前端地圖組件支援聚合資料顯示
  - 移除個別租屋標記模式，專注於區域統計
  - 完成統計概覽面板顯示

- **2025-09-27 21:00**: 完成 .ai-dev 目錄整理
  - 移除過時的 PHP 分析腳本
  - 更新資料結構相關檔案
  - 重新組織目錄結構
  - 更新開發進度追蹤
  - 優化檔案分類和說明

- **2025-09-27 17:45**: 完成目錄整理和分類
  - 移除過時檔案，保留核心工具
  - 建立清晰的目錄結構
  - 優化中文處理問題

## 🎯 最新開發成果

### **地理聚合系統**
- **TaiwanGeoCenterService**: 提供台灣縣市和行政區的地理中心點座標
- **GeoAggregationService**: 聚合租屋資料按縣市行政區統計
- **支援縣市**: 台北市、新北市、桃園市、台中市、台南市、高雄市、新竹市、嘉義市、南投縣、彰化縣、雲林縣

### **地圖系統重構**
- **聚合資料顯示**: 以縣市行政區為單位顯示租屋統計
- **統計概覽面板**: 顯示熱門區域、總租屋數、涵蓋縣市、平均每坪租金
- **兩層選擇器**: 縣市 → 行政區的階層式選擇
- **API 端點**: `/api/map/optimized-data`, `/api/map/cities`, `/api/map/districts`

### **前端組件更新**
- **地圖組件**: 支援聚合資料格式和統計資訊顯示
- **控制面板**: 縣市和行政區選擇器，顯示模式調整
- **彈出視窗**: 顯示區域統計、設施比例等聚合資訊
- **統計卡片**: 頂部概況數據面板

### **資料庫結構**
- **聚合統計**: 物件數量、平均租金、租金範圍、設施比例
- **地理中心點**: 每個縣市行政區的中心座標
- **效能優化**: 從 4448 筆單筆資料優化為 169 個聚合區域
