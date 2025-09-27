const https = require('https');
const path = require('path');

// OAuth 設定
const CLIENT_ID = '7a8573c37786a73a9affd9c04ab46202';
const CLIENT_SECRET = 'fcf427689c053d61a6e22db10cc0663a';
const REDIRECT_URI = 'http://localhost:8000/callback';

// Linear API 端點
const LINEAR_API_URL = 'https://api.linear.app/graphql';
const LINEAR_AUTH_URL = 'https://linear.app/oauth/authorize';

// 儲存 token 的檔案路徑
const PARENT_TOKEN_FILE = path.join(__dirname, '../../../.ai-dev-tools/linear-token.json');
const LOCAL_TOKEN_FILE = path.join(__dirname, 'linear-token.json');

// 讀取儲存的 token
function loadToken() {
  try {
    const fs = require('fs');
    // 優先使用上層目錄的 token 檔案
    if (fs.existsSync(PARENT_TOKEN_FILE)) {
      return JSON.parse(fs.readFileSync(PARENT_TOKEN_FILE, 'utf8'));
    } else if (fs.existsSync(LOCAL_TOKEN_FILE)) {
      return JSON.parse(fs.readFileSync(LOCAL_TOKEN_FILE, 'utf8'));
    }
  } catch (error) {
    console.log('無法讀取 token 檔案');
  }
  return null;
}

// 儲存 token
function saveToken(token) {
  const fs = require('fs');
  // 確保上層目錄存在
  const parentDir = path.dirname(PARENT_TOKEN_FILE);
  if (!fs.existsSync(parentDir)) {
    fs.mkdirSync(parentDir, { recursive: true });
  }
  // 儲存到上層目錄
  fs.writeFileSync(PARENT_TOKEN_FILE, JSON.stringify(token, null, 2));
}

// 取得授權 URL
function getAuthUrl() {
  const params = new URLSearchParams({
    client_id: CLIENT_ID,
    redirect_uri: REDIRECT_URI,
    response_type: 'code',
    scope: 'read write',
    state: 'rentalradar-integration'
  });
  
  return `${LINEAR_AUTH_URL}?${params.toString()}`;
}

// 使用授權碼取得 token
async function getTokenFromCode(code) {
  const tokenUrl = 'https://api.linear.app/oauth/token';
  
  const data = new URLSearchParams({
    grant_type: 'authorization_code',
    client_id: CLIENT_ID,
    client_secret: CLIENT_SECRET,
    redirect_uri: REDIRECT_URI,
    code: code
  }).toString();

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    }
  };

  return new Promise((resolve, reject) => {
    const req = https.request(tokenUrl, options, (res) => {
      let responseData = '';
      res.on('data', (chunk) => responseData += chunk);
      res.on('end', () => {
        try {
          const result = JSON.parse(responseData);
          resolve(result);
        } catch (error) {
          reject(error);
        }
      });
    });

    req.on('error', reject);
    req.write(data);
    req.end();
  });
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

// 列出 Teams 和 Projects
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

  return await makeApiRequest(query);
}

// 建立 Issue
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

  return await makeApiRequest(query, variables);
}

// 主要功能
async function main() {
  const command = process.argv[2];
  const args = process.argv.slice(3);

  try {
    switch (command) {
      case 'auth':
        console.log('🔐 請前往以下 URL 進行授權:');
        console.log(getAuthUrl());
        console.log('\n📋 授權完成後，請複製授權碼並執行:');
        console.log('node linear-oauth-integration.cjs token <授權碼>');
        break;

      case 'token':
        if (args.length < 1) {
          console.log('❌ 請提供授權碼');
          console.log('用法: node linear-oauth-integration.cjs token <授權碼>');
          return;
        }

        console.log('🔄 正在交換授權碼...');
        const tokenResult = await getTokenFromCode(args[0]);
        
        if (tokenResult.access_token) {
          saveToken(tokenResult);
          console.log('✅ OAuth 認證成功！');
          console.log('🎉 現在可以使用其他指令了');
        } else {
          console.log('❌ 認證失敗:', tokenResult);
        }
        break;

      case 'status':
        console.log('🔍 正在檢查 Linear 連接...');
        const teamsResult = await listTeamsAndProjects();
        
        if (teamsResult.errors) {
          console.log('❌ API 錯誤:', teamsResult.errors);
          return;
        }

        console.log('✅ Linear API 連接成功！');
        console.log('📋 可用的 Teams 和 Projects:');
        
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
        break;

      case 'create':
        if (args.length < 3) {
          console.log('❌ 請提供完整參數');
          console.log('用法: node linear-oauth-integration.cjs create <team-id> <title> <description>');
          return;
        }

        console.log('🚀 正在建立 Issue...');
        const issueResult = await createIssue(args[1], args[2], args[0]);
        
        if (issueResult.data && issueResult.data.issueCreate.success) {
          console.log('✅ Issue 建立成功！');
          console.log(`🔗 Issue URL: ${issueResult.data.issueCreate.issue.url}`);
        } else {
          console.log('❌ Issue 建立失敗:', issueResult);
        }
        break;

      default:
        console.log('🏠 RentalRadar Linear 整合工具');
        console.log('\n可用指令:');
        console.log('  auth                    - 取得授權 URL');
        console.log('  token <授權碼>          - 使用授權碼取得 token');
        console.log('  status                  - 顯示 Teams 和 Projects');
        console.log('  create <team-id> <title> <description> - 建立 Issue');
        console.log('\n使用流程:');
        console.log('1. node linear-oauth-integration.cjs auth');
        console.log('2. 前往授權 URL 完成授權');
        console.log('3. node linear-oauth-integration.cjs token <授權碼>');
        console.log('4. node linear-oauth-integration.cjs status');
    }

  } catch (error) {
    console.error('❌ 錯誤:', error.message);
    
    if (error.message.includes('OAuth 認證')) {
      console.log('\n💡 請先執行認證流程:');
      console.log('1. node linear-oauth-integration.cjs auth');
      console.log('2. 前往授權 URL 完成授權');
      console.log('3. node linear-oauth-integration.cjs token <授權碼>');
    }
  }
}

main();
