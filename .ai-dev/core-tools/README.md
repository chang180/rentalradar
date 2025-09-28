# Linear 管理工具

## 📋 可用工具

### 1. `linear-oauth-integration.cjs` - 主要工具
**用途**: Linear 管理的完整功能工具，包含 OAuth 認證和 Issues 管理
```bash
node linear-oauth-integration.cjs <command> [args]
```

**可用指令**:
- `auth` - 取得授權 URL
- `token <授權碼>` - 使用授權碼取得 token
- `status` - 顯示 Teams 和 Projects
- `list` - 列出所有 Issues
- `states` - 列出可用狀態
- `update <issue-id> <state-id>` - 更新 Issue 狀態
- `edit <issue-id> <title> <description>` - 更新 Issue 內容
- `create <team-id> <title> <description>` - 建立 Issue
- `delete <issue-id>` - 刪除 Issue

### 2. `test-linear-api.cjs` - API 測試工具
**用途**: 測試 Linear API 連接和功能
```bash
node test-linear-api.cjs
```

## 🔧 使用流程

### 首次設定
1. **取得授權**: `node linear-oauth-integration.cjs auth`
2. **完成授權**: 前往授權 URL 完成授權
3. **取得 token**: `node linear-oauth-integration.cjs token <授權碼>`
4. **驗證狀態**: `node linear-oauth-integration.cjs status`

### 日常使用
1. **查看 Issues**: `node linear-oauth-integration.cjs list`
2. **查看狀態**: `node linear-oauth-integration.cjs states`
3. **更新狀態**: `node linear-oauth-integration.cjs update <issue-id> <state-id>`
4. **建立 Issue**: `node linear-oauth-integration.cjs create <team-id> <title> <description>`

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
node linear-oauth-integration.cjs list

# 查看可用狀態
node linear-oauth-integration.cjs states

# 更新 Issue 狀態 (使用 Issue ID 和狀態 ID)
node linear-oauth-integration.cjs update <issue-id> <state-id>

# 建立新的 Issue
node linear-oauth-integration.cjs create <team-id> "Issue Title" "Issue Description"
```

## 🚀 快速更新 Issue 狀態

```bash
# 使用主要工具更新狀態
node linear-oauth-integration.cjs update <issue-id> <state-id>

# 常用狀態 ID:
# - Done: 9fbe935a-aff3-4627-88a3-74353a55c221
# - In Progress: a8c3ca26-39f0-4728-93ba-4130050d1abe
```

## 📋 故障排除

### Token 認證問題
1. 檢查 token 檔案是否存在: `../.ai-dev-tools/linear-token.json`
2. 重新進行 OAuth 認證流程
3. 確認 token 未過期
4. 或使用 .env 中的 LINEAR_API_TOKEN

### API 錯誤
1. 檢查網路連接
2. 確認 Linear API 可用性
3. 檢查 token 權限
4. 確認環境變數設定正確

### 常用 Team ID
- **DevStream-Core**: `40b1bdfd-2caa-4306-9fc4-8c4f2d646cec`

---
**最後更新**: 2025-09-28  
**維護者**: Claude (架構師)
