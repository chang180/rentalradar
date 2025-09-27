const https = require('https');
const fs = require('fs');
const path = require('path');

// 讀取儲存的 token
function loadToken() {
  try {
    if (fs.existsSync(path.join(__dirname, 'linear-token.json'))) {
      return JSON.parse(fs.readFileSync(path.join(__dirname, 'linear-token.json'), 'utf8'));
    }
  } catch (error) {
    console.log('無法讀取 token 檔案');
  }
  return null;
}

// 使用 token 進行 API 請求
async function makeApiRequest(query, variables = {}) {
  const token = loadToken();
  
  if (!token || !token.access_token) {
    throw new Error('需要先進行 OAuth 認證');
  }

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token.access_token}`
    }
  };

  return new Promise((resolve, reject) => {
    const req = https.request('https://api.linear.app/graphql', options, (res) => {
      let data = '';
      res.on('data', (chunk) => data += chunk);
      res.on('end', () => {
        try {
          const result = JSON.parse(data);
          resolve(result);
        } catch (error) {
          reject(error);
        }
      });
    });

    req.on('error', reject);
    req.write(JSON.stringify({ query, variables }));
    req.end();
  });
}

// 更新 Issue 標題和描述
async function updateIssue(issueId, title, description) {
  const query = `
    mutation UpdateIssue($id: String!, $input: IssueUpdateInput!) {
      issueUpdate(id: $id, input: $input) {
        success
        issue {
          id
          identifier
          title
          description
        }
      }
    }
  `;

  const variables = {
    id: issueId,
    input: {
      title: title,
      description: description
    }
  };

  return await makeApiRequest(query, variables);
}

// 主要功能
async function main() {
  try {
    console.log('🔧 正在修正 Issue DEV-6 的中文編碼問題...');
    
    const result = await updateIssue(
      'DEV-6',
      '測試 Claude 指派任務',
      `測試多 AI 協作功能，指派給 Claude Code 執行

## 任務描述
- 建立一個簡單的 Laravel 服務類別
- 實作基本的資料處理功能
- 撰寫對應的測試
- 驗證多 AI 協作流程

## 技術要求
- 使用 Laravel 12 + PHP 8.4
- 遵循 PSR-12 編碼標準
- 撰寫完整的 PHPDoc 註解
- 包含 Pest 測試

## 指派資訊
- 指派給: Claude Code (代碼專家)
- 優先級: 高
- 預估時間: 30分鐘

## 驗證目標
1. 角色扮演 - Claude Code 是否以代碼專家身份回應
2. 技術能力 - 是否能提供高品質的代碼實作
3. 協作流程 - 是否能理解任務並執行
4. 品質標準 - 是否符合設定的技術要求`
    );
    
    if (result.data && result.data.issueUpdate.success) {
      console.log('✅ Issue 標題和描述已修正！');
      console.log(`📋 新標題: ${result.data.issueUpdate.issue.title}`);
      console.log(`🔗 Issue URL: https://linear.app/devstream-core/issue/DEV-6/測試-claude-指派任務`);
    } else {
      console.log('❌ 更新失敗:', result);
    }

  } catch (error) {
    console.error('❌ 錯誤:', error.message);
  }
}

main();
