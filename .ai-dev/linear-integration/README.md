# Linear 整合工具集

這個目錄包含了 RentalRadar 專案的 Linear 整合工具。

## 📁 檔案說明

### **核心整合工具**
- `linear-oauth-integration.cjs` - OAuth 認證和基本 API 操作
- `linear-issues.cjs` - Issues 管理工具
- `complete-linear-tasks.cjs` - 批量完成 Linear 任務
- `project-manager.cjs` - 本地專案管理工具

### **備份和舊版工具**
- `project-status.json` - 本地專案管理備份 (Linear 連接失敗時使用)
- `linear-integration.cjs` - 舊版 API key 整合
- `linear-integration.js` - 舊版 JavaScript 版本

## 🚀 使用方式

### **1. OAuth 認證**
```bash
# 取得授權 URL
node .ai-dev/linear-integration/linear-oauth-integration.cjs auth

# 使用授權碼取得 token
node .ai-dev/linear-integration/linear-oauth-integration.cjs token <授權碼>
```

### **2. 查看 Issues**
```bash
node .ai-dev/linear-integration/linear-issues.cjs list
```

### **3. 本地專案管理**
```bash
node .ai-dev/linear-integration/project-manager.cjs status
```

### **4. 批量完成任務**
```bash
node .ai-dev/linear-integration/complete-linear-tasks.cjs complete
```

## 🔧 設定

### **OAuth 設定**
- Client ID: `7a8573c37786a73a9affd9c04ab46202`
- Client Secret: `fcf427689c053d61a6e22db10cc0663a`
- Redirect URI: `http://localhost:8000/callback`

### **專案資訊**
- Team ID: `40b1bdfd-2caa-4306-9fc4-8c4f2d646cec`
- Project ID: `d7bd332e-2166-4d2f-ba5c-bfd4f01422c5`

## 📋 工作流程

1. **開發前**: 使用 `project-manager.cjs` 規劃任務
2. **開發中**: 使用 `linear-issues.cjs` 追蹤進度
3. **完成後**: 使用 `complete-linear-tasks.cjs` 更新狀態

## 🤖 AI 團隊協作

這些工具支援多 AI 協作開發：
- **Claude**: 專案架構和規劃
- **Claude Code**: 具體功能實作
- **Codex**: AI 演算法和資料科學

每個 AI 都可以使用這些工具來同步 Linear 狀態。
