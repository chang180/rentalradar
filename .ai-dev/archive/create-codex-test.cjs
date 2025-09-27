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

// 建立 Issue
async function createIssue(title, description, teamId) {
  const query = `
    mutation CreateIssue($input: IssueCreateInput!) {
      issueCreate(input: $input) {
        success
        issue {
          id
          identifier
          title
          description
          url
        }
      }
    }
  `;

  const variables = {
    input: {
      title,
      description,
      teamId
    }
  };

  return await makeApiRequest(query, variables);
}

// 主要功能
async function main() {
  try {
    console.log('🚀 正在建立 Codex 測試任務...');
    
    const title = '測試 Codex AI 演算法開發';
    const description = `測試 Codex 的 AI 演算法開發能力，專注於資料科學和機器學習

## 任務描述
- 開發一個簡單的異常值檢測演算法
- 實作基本的統計分析功能
- 建立資料預處理管道
- 驗證 AI 演算法開發流程

## 技術要求
- 使用 Python 或 PHP 實作
- 實作統計學方法 (Z-score, IQR)
- 提供演算法效能指標
- 包含使用範例和文檔
- 預估時間: 45分鐘

## 指派資訊
- 指派給: Codex (AI 演算法專家)
- 優先級: 高
- 類型: AI 演算法開發
- 驗證目標: AI 演算法設計能力、資料科學技能、效能優化

## 預期輸出
1. 完整的異常值檢測演算法
2. 統計分析功能實作
3. 效能測試和指標
4. 使用文檔和範例
5. 演算法優化建議`;

    const result = await createIssue(title, description, '40b1bdfd-2caa-4306-9fc4-8c4f2d646cec');
    
    if (result.data && result.data.issueCreate.success) {
      console.log('✅ Codex 測試任務建立成功！');
      console.log(`📋 Issue: ${result.data.issueCreate.issue.identifier}`);
      console.log(`🔗 URL: ${result.data.issueCreate.issue.url}`);
    } else {
      console.log('❌ 建立失敗:', result);
    }

  } catch (error) {
    console.error('❌ 錯誤:', error.message);
  }
}

main();
