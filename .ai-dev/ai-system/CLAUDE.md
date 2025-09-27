# 🤖 RentalRadar AI 開發團隊指南

## 專案概述

**RentalRadar** 是一個基於 AI 技術的租賃市場分析平台，整合政府開放資料，提供智慧化的租屋市場洞察。

## 技術架構

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

## 開發團隊架構

### **Claude - 專案架構師**
- 🎯 **主要職責**: 專案規劃、架構設計、代碼審查
- 🔧 **技術棧**: Laravel 後端、React 前端、AI 演算法設計
- 📋 **工作範圍**: 核心功能開發、系統整合、品質控制

### **Claude Code - 代碼專家**
- 🎯 **主要職責**: 具體功能實作、代碼優化、測試撰寫
- 🔧 **技術棧**: PHP/Laravel、JavaScript/React、資料庫設計
- 📋 **工作範圍**: API 開發、前端組件、資料處理邏輯

### **Codex - AI 演算法專家**
- 🎯 **主要職責**: AI 演算法、資料科學、效能優化
- 🔧 **技術棧**: 機器學習、資料分析、演算法優化
- 📋 **工作範圍**: AI 資料清理、異常檢測、統計分析

## 當前開發階段

### **Phase 2: AI 資料處理** (進行中)
- **主要負責**: Codex
- **協作**: Claude, Claude Code
- **任務**:
  - [x] 建立 AI 資料清理服務類別
  - [ ] 實作 CSV 和 XML 格式處理
  - [ ] 開發異常值檢測演算法
  - [ ] 建立資料驗證規則
  - [ ] 優化處理效能

## 開發工作流程

### **1. 任務分配**
```bash
# 查看 Linear Issues
node .ai-dev/linear-integration/linear-issues.cjs list

# 查看本地專案狀態
node .ai-dev/linear-integration/project-manager.cjs status
```

### **2. 開發流程**
1. **Claude** 規劃架構和設計
2. **Claude Code** 實作具體功能
3. **Codex** 開發 AI 演算法
4. **Claude** 進行代碼審查和整合

### **3. 進度追蹤**
```bash
# 更新 Linear Issues 狀態
node .ai-dev/linear-integration/linear-cli.cjs update <issue-id> <state-id>

# 批量完成任務
node .ai-dev/linear-integration/linear-cli.cjs complete
```

## 程式碼風格指南

### **PHP/Laravel**
- 使用 PHP 8.4 新特性
- 遵循 PSR-12 編碼標準
- 使用 Laravel 最佳實踐
- 撰寫完整的 PHPDoc 註解

### **JavaScript/React**
- 使用 TypeScript
- 遵循 ESLint 規則
- 使用 Tailwind CSS v4
- 實作響應式設計

### **AI 演算法**
- 使用機器學習最佳實踐
- 實作效能優化
- 提供詳細的演算法文檔
- 確保可重現性

## 常用指令

### **Laravel 開發**
```bash
# 建立新服務
php artisan make:class Services/ServiceName

# 建立測試
php artisan make:test --pest FeatureName

# 執行測試
php artisan test

# 代碼格式化
vendor/bin/pint --dirty
```

### **前端開發**
```bash
# 開發伺服器
npm run dev

# 建置專案
npm run build

# 代碼檢查
npm run lint

# 格式化
npm run format
```

### **Linear 整合**
```bash
# 查看 Issues
node .ai-dev/linear-integration/linear-cli.cjs list

# 更新狀態
node .ai-dev/linear-integration/linear-cli.cjs update <issue-id> <state-id>
```

## 專案目標

### **技術指標**
- AI 資料處理準確率 > 95%
- 地圖載入速度 < 2秒
- AI 統計分析響應 < 1秒
- 系統穩定性 > 99%

### **展示指標**
- 政府平台活化應用上架
- 作品集網站整合
- 技術文檔完整
- Demo 展示影片

## 注意事項

1. **安全性**: 不要將 API keys 或敏感資訊提交到 Git
2. **效能**: 注意 AI 演算法的效能優化
3. **測試**: 每個功能都要有對應的測試
4. **文檔**: 保持程式碼和文檔的同步更新
5. **協作**: 透過 Linear Issues 同步進度

---

**最後更新**: 2025-09-27  
**版本**: 1.0.0  
**維護者**: RentalRadar AI 開發團隊
