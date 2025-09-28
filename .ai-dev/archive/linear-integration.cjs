const https = require('https');
const fs = require('fs');
const path = require('path');

// 安全地從 .env 讀取環境變數（不存儲在檔案中）
function getEnvVariable(varName) {
  // 首先檢查 process.env
  if (process.env[varName]) {
    return process.env[varName];
  }

  // 如果環境變數不存在，嘗試從 .env 檔案讀取
  try {
    const envPath = path.join(__dirname, '../../.env');
    const envContent = fs.readFileSync(envPath, 'utf8');

    const lines = envContent.split('\n');
    for (const line of lines) {
      const trimmedLine = line.trim();
      if (trimmedLine.startsWith(`${varName}=`)) {
        return trimmedLine.split('=')[1].trim();
      }
    }
  } catch (error) {
    console.log(`⚠️  無法讀取 ${varName} 環境變數:`, error.message);
  }

  return null;
}

// 動態獲取 token，不存儲在檔案中
function getLinearToken() {
  return getEnvVariable('LINEAR_API_TOKEN');
}

// Linear API 端點
const LINEAR_API_URL = 'https://api.linear.app/graphql';

// 建立 Issue 的函數
async function createIssue(title, description, teamId) {
  const query = `
    mutation CreateIssue($input: IssueCreateInput!) {
      issueCreate(input: $input) {
        success
        issue {
          id
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

  const linearToken = getLinearToken();
  if (!linearToken) {
    throw new Error('LINEAR_API_TOKEN 不存在於環境變數中');
  }

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${linearToken}`
    }
  };

  return new Promise((resolve, reject) => {
    const req = https.request(LINEAR_API_URL, options, (res) => {
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

// 列出 Teams 和 Projects 的函數
async function listTeamsAndProjects() {
  const query = `
    query {
      teams {
        nodes {
          id
          name
          projects {
            nodes {
              id
              name
              description
              state
            }
          }
        }
      }
    }
  `;

  const linearToken = getLinearToken();
  if (!linearToken) {
    throw new Error('LINEAR_API_TOKEN 不存在於環境變數中');
  }

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${linearToken}`
    }
  };

  return new Promise((resolve, reject) => {
    const req = https.request(LINEAR_API_URL, options, (res) => {
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
    req.write(JSON.stringify({ query }));
    req.end();
  });
}

// 主要功能
async function main() {
  try {
    // 檢查 token 是否可以取得
    const testToken = getLinearToken();
    if (!testToken) {
      console.log('❌ 無法取得 LINEAR_API_TOKEN 環境變數');
      console.log('💡 請確認 .env 檔案中有設定 LINEAR_API_TOKEN 或在環境變數中設定');
      return;
    }

    console.log('✅ LINEAR_API_TOKEN 環境變數取得成功');
    console.log('🔍 正在檢查 Linear API 連接...');
    
    // 列出 Teams 和 Projects
    const teamsResult = await listTeamsAndProjects();
    console.log('✅ Linear API 連接成功！');
    console.log('📋 可用的 Teams 和 Projects:');
    console.log('🔍 API 回應:', JSON.stringify(teamsResult, null, 2));
    
    if (teamsResult.data && teamsResult.data.teams) {
      teamsResult.data.teams.nodes.forEach(team => {
        console.log(`\n🏢 Team: ${team.name} (ID: ${team.id})`);
        if (team.projects && team.projects.nodes.length > 0) {
          team.projects.nodes.forEach(project => {
            console.log(`  📁 Project: ${project.name} (ID: ${project.id})`);
            console.log(`     Description: ${project.description || 'No description'}`);
            console.log(`     State: ${project.state}`);
          });
        } else {
          console.log('  📁 No projects found');
        }
      });
    }

    // 建立測試 Issue
    if (teamsResult.data && teamsResult.data.teams.nodes.length > 0) {
      const firstTeam = teamsResult.data.teams.nodes[0];
      console.log(`\n🚀 正在建立測試 Issue 到 Team: ${firstTeam.name}...`);
      
      const issueResult = await createIssue(
        '測試 Issue - RentalRadar 專案',
        '這是一個測試 Issue，用於驗證 Linear API 整合。',
        firstTeam.id
      );
      
      if (issueResult.data && issueResult.data.issueCreate.success) {
        console.log('✅ Issue 建立成功！');
        console.log(`🔗 Issue URL: ${issueResult.data.issueCreate.issue.url}`);
      } else {
        console.log('❌ Issue 建立失敗:', issueResult);
      }
    }

  } catch (error) {
    console.error('❌ 錯誤:', error.message);
    console.log('\n💡 可能的解決方案:');
    console.log('1. 檢查 API Key 是否正確');
    console.log('2. 確認 Linear 帳號權限');
    console.log('3. 檢查網路連接');
  }
}

// 執行主函數
main();
