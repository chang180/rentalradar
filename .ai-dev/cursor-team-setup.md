# 🚀 Cursor 多 AI 協作設定指南

## 設定 Claude Code 加入開發團隊

### **步驟 1：建立 Cursor 工作區設定**

1. **開啟 Cursor 設定**
   - 按 `Ctrl+,` (Windows) 或 `Cmd+,` (Mac)
   - 搜尋 "Claude" 或 "AI"

2. **啟用 Claude Code 功能**
   - 確保 Claude Code 已啟用
   - 設定 API 金鑰和權限

### **步驟 2：建立團隊協作配置**

#### **方法 A：使用 Cursor 工作區設定**
```json
// .vscode/settings.json
{
  "claude.teamMode": true,
  "claude.collaboration": {
    "enabled": true,
    "teamMembers": [
      {
        "name": "Claude",
        "role": "專案架構師",
        "permissions": ["read", "write", "review"]
      },
      {
        "name": "Claude Code",
        "role": "代碼專家", 
        "permissions": ["read", "write", "test"]
      },
      {
        "name": "Codex",
        "role": "AI 演算法專家",
        "permissions": ["read", "write", "ai-algorithms"]
      }
    ]
  }
}
```

#### **方法 B：使用 Cursor 指令**
```bash
# 在 Cursor 中執行
@claude-team setup
@claude-code join-team
@codex configure-ai
```

### **步驟 3：設定專案權限**

1. **建立 `.cursor/team-config.json`**
```json
{
  "team": {
    "name": "RentalRadar AI Team",
    "members": [
      {
        "id": "claude",
        "name": "Claude",
        "role": "architect",
        "permissions": ["full-access"]
      },
      {
        "id": "claude-code", 
        "name": "Claude Code",
        "role": "developer",
        "permissions": ["code-access", "test-access"]
      },
      {
        "id": "codex",
        "name": "Codex", 
        "role": "ai-specialist",
        "permissions": ["ai-access", "data-access"]
      }
    ]
  },
  "workflow": {
    "codeReview": true,
    "autoTesting": true,
    "aiOptimization": true
  }
}
```

### **步驟 4：設定協作工作流程**

#### **任務分配流程**
1. **Claude** 在 Linear 中建立 Issues
2. **Claude Code** 實作具體功能
3. **Codex** 開發 AI 演算法
4. **Claude** 進行代碼審查

#### **溝通協作**
- 使用 Linear Issues 作為主要溝通平台
- 透過 Git commits 同步進度
- 使用 Cursor 的 AI 聊天功能進行即時協作

### **步驟 5：測試協作功能**

#### **測試指令**
```bash
# 測試 Linear 整合
node .ai-dev/linear-integration/linear-cli.cjs list

# 測試專案管理
node .ai-dev/linear-integration/project-manager.cjs status

# 測試 AI 協作
@claude-team status
@claude-code test-feature
@codex analyze-data
```

## 實際操作步驟

### **1. 在 Cursor 中設定**

1. **開啟 Cursor**
2. **按 `Ctrl+Shift+P` 開啟命令面板**
3. **搜尋 "Claude" 或 "AI"**
4. **選擇 "Configure Team Collaboration"**

### **2. 邀請 Claude Code**

1. **在 Cursor 中按 `Ctrl+Shift+P`**
2. **搜尋 "Invite AI Assistant"**
3. **選擇 "Claude Code"**
4. **設定權限和角色**

### **3. 設定 Codex**

1. **在 Cursor 中按 `Ctrl+Shift+P`**
2. **搜尋 "Configure AI Specialist"**
3. **選擇 "Codex"**
4. **設定 AI 演算法權限**

## 驗證設定

### **檢查清單**
- [ ] Claude Code 已加入團隊
- [ ] Codex 已設定 AI 權限
- [ ] Linear 整合正常運作
- [ ] 專案管理工具可用
- [ ] 團隊協作流程順暢

### **測試指令**
```bash
# 測試所有工具
node .ai-dev/linear-integration/linear-cli.cjs status
node .ai-dev/linear-integration/project-manager.cjs status

# 測試 AI 協作
@claude-team list-members
@claude-code check-permissions
@codex test-ai-functions
```

## 注意事項

1. **權限管理**: 確保每個 AI 都有適當的權限
2. **安全設定**: 不要暴露敏感的 API 金鑰
3. **效能監控**: 注意 AI 協作的效能影響
4. **成本控制**: 監控 AI 使用成本
5. **品質保證**: 確保代碼品質和測試覆蓋率

---

**設定完成後，你就可以開始多 AI 協作開發了！**
