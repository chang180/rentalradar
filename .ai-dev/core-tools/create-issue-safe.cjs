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
  const args = process.argv.slice(2);
  
  if (args.length < 2) {
    console.log('用法: node create-issue-safe.cjs <title> <description>');
    console.log('範例: node create-issue-safe.cjs "測試任務" "這是一個測試任務"');
    return;
  }

  const title = args[0];
  const description = args[1];

  try {
    console.log('🚀 正在建立 Issue...');
    console.log(`📋 標題: ${title}`);
    console.log(`📝 描述: ${description}`);
    
    const result = await createIssue(title, description, '40b1bdfd-2caa-4306-9fc4-8c4f2d646cec');
    
    if (result.data && result.data.issueCreate.success) {
      console.log('✅ Issue 建立成功！');
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
