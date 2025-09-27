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
