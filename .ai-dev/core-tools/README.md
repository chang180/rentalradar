# Linear 管理工具

## 📋 可用工具

### 1. `linear-cli.cjs` - 主控制台
**用途**: Linear 管理的主要入口點
```bash
node linear-cli.cjs <command> [args]
```

**可用指令**:
- `auth` - 取得授權 URL
- `token <授權碼>` - 使用授權碼取得 token
- `status` - 顯示 Teams 和 Projects
- `list` - 列出所有 Issues
- `states` - 列出可用狀態
- `update <issue-id> <state-id>` - 更新 Issue 狀態

### 2. `linear-issues.cjs` - Issues 管理
**用途**: 直接管理 Linear Issues
```bash
node linear-issues.cjs <command> [args]
```

**可用指令**:
- `list` - 列出所有 Issues
- `states` - 列出可用狀態
- `update <issue-id> <state-id>` - 更新 Issue 狀態

### 3. `linear-oauth-integration.cjs` - OAuth 認證
**用途**: 處理 Linear OAuth 認證流程
```bash
node linear-oauth-integration.cjs <command> [args]
```

**可用指令**:
- `auth` - 取得授權 URL
- `token <授權碼>` - 使用授權碼取得 token
- `status` - 顯示 Teams 和 Projects

## 🔧 使用流程

### 首次設定
1. **取得授權**: `node linear-oauth-integration.cjs auth`
2. **完成授權**: 前往授權 URL 完成授權
3. **取得 token**: `node linear-oauth-integration.cjs token <授權碼>`
4. **驗證狀態**: `node linear-oauth-integration.cjs status`

### 日常使用
1. **查看 Issues**: `node linear-issues.cjs list`
2. **查看狀態**: `node linear-issues.cjs states`
3. **更新狀態**: `node linear-issues.cjs update DEV-XX Done`

## 📝 重要注意事項

### Token 位置
- **主要位置**: `../.ai-dev-tools/linear-token.json`
- **備用位置**: `./linear-token.json`

### 常用狀態 ID
- **In Progress**: `a8c3ca26-39f0-4728-93ba-4130050d1abe`
- **Done**: `9fbe935a-aff3-4627-88a3-74353a55c221`

### 範例指令
```bash
# 查看所有 Issues
node linear-issues.cjs list

# 查看可用狀態
node linear-issues.cjs states

# 更新 DEV-23 為 Done
node linear-issues.cjs update DEV-23 Done
```

## 🚀 快速更新 Issue 狀態

```bash
# 使用主控制台
node linear-cli.cjs update DEV-XX Done

# 或直接使用 issues 工具
node linear-issues.cjs update DEV-XX Done
```

## 📋 故障排除

### Token 認證問題
1. 檢查 token 檔案是否存在: `../.ai-dev-tools/linear-token.json`
2. 重新進行 OAuth 認證流程
3. 確認 token 未過期

### API 錯誤
1. 檢查網路連接
2. 確認 Linear API 可用性
3. 檢查 token 權限

---
**最後更新**: 2025-01-XX  
**維護者**: Claude (架構師)
