# 🤖 AI 開發工具目錄

## 📁 目錄結構

### **core-tools/** - 核心工具
- `linear-oauth-integration.cjs` - OAuth 認證和基本 API 操作
- `linear-issues.cjs` - Issues 管理工具
- `linear-cli.cjs` - 統一指令工具
- `anomaly_detection.py` - 異常值檢測工具 (Codex 開發)
- `create-issue-from-json.cjs` - JSON 方式建立 Issue (中文安全)
- `create-issue-safe.cjs` - 安全腳本
- `issue-template.json` - Issue 模板
- `linear-token.json` - OAuth token (敏感檔案)

### **ai-system/** - AI 協作系統
- `ai-team-config.json` - AI 團隊設定
- `AI-TEAM-GUIDE.md` - AI 團隊協作指南
- `CLAUDE.md` - 專案開發指南
- `ai-roles/` - AI 角色提示檔案
  - `claude-code-prompt.md` - Claude Code 角色提示
  - `codex-prompt.md` - Codex 角色提示

### **archive/** - 封存檔案
- 過時的腳本和文檔
- 一次性使用的工具
- 舊版本的管理工具

## 🚀 使用方式

### **基本操作**
```bash
# 查看 Linear Issues
node .ai-dev/core-tools/linear-issues.cjs list

# 建立 Issue (JSON 方式，中文安全)
node .ai-dev/core-tools/create-issue-from-json.cjs

# 使用異常值檢測工具
python .ai-dev/core-tools/anomaly_detection.py --demo
```

### **AI 協作**
1. 參考 `ai-system/AI-TEAM-GUIDE.md` 了解協作流程
2. 使用 `ai-system/ai-roles/` 中的角色提示
3. 透過 Linear Issues 管理任務

## 🔧 維護說明

- **core-tools/**: 經常使用的核心工具
- **ai-system/**: AI 協作相關設定和指南
- **archive/**: 過時檔案，可定期清理

## 📝 更新日誌

- **2025-09-27**: 完成目錄整理和分類
- 移除過時檔案，保留核心工具
- 建立清晰的目錄結構
- 優化中文處理問題
