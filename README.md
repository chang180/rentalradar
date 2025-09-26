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
- **異常值檢測**: 機器學習演算法
- **統計分析**: 深度數據挖掘
- **地圖優化**: 效能調校和渲染優化

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

### Phase 2: AI 資料處理 🔄
- [ ] AI 資料清理演算法
- [ ] AI 地理編碼系統
- [ ] AI 異常值檢測
- [ ] 政府資料下載機制

### Phase 3: AI 地圖系統 📋
- [ ] Leaflet.js 整合
- [ ] AI 優化地圖渲染
- [ ] 熱力圖功能
- [ ] 互動式標記

### Phase 4: AI 統計分析 📋
- [ ] 趨勢預測演算法
- [ ] 市場分析功能
- [ ] 推薦系統
- [ ] 效能優化

### Phase 5: 使用者回報系統 📋
- [ ] 使用者註冊驗證
- [ ] 權重計算機制
- [ ] 信譽評分系統
- [ ] 資料品質控制

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

### 2025-01-27
- ✅ 專案初始化完成
- ✅ Laravel 12 + React 環境建立
- ✅ Git 倉庫設定完成
- ✅ README.md 建立

### 2025-01-27 (下午)
- ✅ 使用者認證系統中文化完成
  - 密碼確認頁面 (`/user/confirm-password`) 中文化
  - 雙重驗證設定頁面 (`/settings/two-factor`) 中文化
  - 個人資料設定頁面 (`/settings/profile`) 中文化
  - 密碼設定頁面 (`/settings/password`) 中文化
  - 外觀設定頁面 (`/settings/appearance`) 中文化
  - 刪除帳號卡片組件中文化
- ✅ 所有設定頁面文字內容改為繁體中文
- ✅ 使用者介面完全中文化，提升台灣用戶體驗
- ✅ 應用程式設定調整為台灣地區
  - 時區設定為 `Asia/Taipei` (台北時間)
  - 語言設定為 `zh_TW` (繁體中文)
  - 應用程式名稱改為 `RentalRadar`
  - 郵件設定更新為台灣地區適用的預設值
  - 環境變數設定在 `.env` 文件中，消除 IDE 警告

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
