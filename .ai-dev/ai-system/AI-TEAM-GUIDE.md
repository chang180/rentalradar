# 🤖 AI 團隊協作指南

## 團隊架構

### **Claude - 專案架構師**
- 🎯 **主要職責**：專案規劃、架構設計、代碼審查
- 🔧 **技術棧**：Laravel 後端、React 前端、AI 演算法設計
- 📋 **工作範圍**：核心功能開發、系統整合、品質控制

### **Claude Code - 代碼專家**
- 🎯 **主要職責**：具體功能實作、代碼優化、測試撰寫
- 🔧 **技術棧**：PHP/Laravel、JavaScript/React、資料庫設計
- 📋 **工作範圍**：API 開發、前端組件、資料處理邏輯

### **Codex - AI 演算法專家**
- 🎯 **主要職責**：AI 演算法、資料科學、效能優化
- 🔧 **技術棧**：機器學習、資料分析、演算法優化
- 📋 **工作範圍**：AI 資料清理、異常檢測、統計分析

## 🚀 協作流程

### **1. 任務分配**
```bash
# 查看當前任務
node .ai-dev/linear-integration/linear-cli.cjs list

# 查看專案狀態
node .ai-dev/linear-integration/linear-cli.cjs project status
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

## 📋 當前專案狀態

### **Phase 2: AI 資料處理** (進行中)
- **主要負責**: Codex
- **協作**: Claude, Claude Code
- **任務**:
  - [x] 建立 AI 資料清理服務類別
  - [ ] 實作 CSV 和 XML 格式處理
  - [ ] 開發異常值檢測演算法
  - [ ] 建立資料驗證規則
  - [ ] 優化處理效能

## 🔧 工具使用

### **Linear 整合工具**
```bash
# 基本操作
node .ai-dev/linear-integration/linear-cli.cjs auth
node .ai-dev/linear-integration/linear-cli.cjs status
node .ai-dev/linear-integration/linear-cli.cjs list
```

### **專案管理工具**
```bash
# 本地專案管理
node .ai-dev/linear-integration/linear-cli.cjs project status
node .ai-dev/linear-integration/linear-cli.cjs project show <issue-id>
```

## 🎯 下一步行動

1. **Codex** 開始實作 AI 資料清理演算法
2. **Claude Code** 協助建立資料處理 API
3. **Claude** 進行架構審查和整合

## 📞 溝通協作

- **Linear Issues**: 主要任務追蹤平台
- **本地專案管理**: 詳細進度追蹤
- **代碼審查**: 透過 Git 和 Linear 整合

每個 AI 都可以獨立工作，但需要透過 Linear 同步進度。
