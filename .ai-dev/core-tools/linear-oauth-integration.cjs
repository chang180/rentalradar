const https = require('https');
const path = require('path');
const fs = require('fs');

// 安全地從環境變數讀取 OAuth 設定
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

// OAuth 設定（從環境變數讀取）
const CLIENT_ID = getEnvVariable('LINEAR_CLIENT_ID');
const CLIENT_SECRET = getEnvVariable('LINEAR_CLIENT_SECRET');
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
  // 優先使用 .env 中的 LINEAR_API_TOKEN
  const envToken = getEnvVariable('LINEAR_API_TOKEN');

  let authHeader;
  if (envToken) {
    authHeader = `Bearer ${envToken}`;
  } else {
    // 備用方案：使用 OAuth token
    const oauthToken = loadToken();
    if (!oauthToken || !oauthToken.access_token) {
      throw new Error('需要先進行 OAuth 認證或在 .env 中設定 LINEAR_API_TOKEN');
    }
    authHeader = `Bearer ${oauthToken.access_token}`;
  }

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': authHeader
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

// 列出 Issues
async function listIssues() {
  const query = `
    query {
      issues {
        nodes {
          id
          identifier
          title
          description
          state {
            name
            type
          }
          priority
          assignee {
            name
            email
          }
          createdAt
          updatedAt
          url
        }
      }
    }
  `;

  return await makeApiRequest(query);
}

// 取得所有狀態
async function getStates() {
  const query = `
    query {
      workflowStates {
        nodes {
          id
          name
          type
        }
      }
    }
  `;

  return await makeApiRequest(query);
}

// 更新 Issue 狀態
async function updateIssueStatus(issueId, stateId) {
  const query = `
    mutation UpdateIssue($id: String!, $input: IssueUpdateInput!) {
      issueUpdate(id: $id, input: $input) {
        success
        issue {
          id
          title
          state {
            name
          }
        }
      }
    }
  `;

  const variables = {
    id: issueId,
    input: {
      stateId: stateId
    }
  };

  return await makeApiRequest(query, variables);
}

async function updateIssueContent(issueId, title, description) {
  const query = `
    mutation UpdateIssue($id: String!, $input: IssueUpdateInput!) {
      issueUpdate(id: $id, input: $input) {
        success
        issue {
          id
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

// 刪除 Issue
async function deleteIssue(issueId) {
  const query = `
    mutation DeleteIssue($id: String!) {
      issueDelete(id: $id) {
        success
      }
    }
  `;

  const variables = {
    id: issueId
  };

  return await makeApiRequest(query, variables);
}

// 主要功能
async function main() {
  const command = process.argv[2];
  const args = process.argv.slice(3);

  // 檢查必要的環境變數
  if (!CLIENT_ID || !CLIENT_SECRET) {
    console.log('❌ 缺少必要的環境變數');
    console.log('💡 請確認 .env 檔案中有設定:');
    console.log('   LINEAR_CLIENT_ID');
    console.log('   LINEAR_CLIENT_SECRET');
    return;
  }

  try {
    switch (command) {
      case 'auth':
        console.log('✅ 環境變數載入成功');
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
        // 檢查並顯示使用的 token 類型
        const envToken = getEnvVariable('LINEAR_API_TOKEN');
        const oauthToken = loadToken();

        if (envToken) {
          console.log('🔑 使用 .env 中的 LINEAR_API_TOKEN');
        } else if (oauthToken && oauthToken.access_token) {
          console.log('🔑 使用 OAuth token');
        } else {
          console.log('❌ 無可用的 token');
          console.log('💡 請設定 LINEAR_API_TOKEN 或執行 OAuth 認證');
          return;
        }

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

      case 'list':
        console.log('🔍 正在取得 Linear Issues...');
        const issuesResult = await listIssues();
        
        if (issuesResult.errors) {
          console.log('❌ API 錯誤:', issuesResult.errors);
          return;
        }

        console.log('✅ Linear Issues 載入成功！\n');
        
        if (issuesResult.data && issuesResult.data.issues) {
          const issues = issuesResult.data.issues.nodes;
          
          if (issues.length === 0) {
            console.log('📋 目前沒有 Issues');
            return;
          }

          // 按狀態分組
          const groupedIssues = {};
          issues.forEach(issue => {
            const stateName = issue.state.name;
            if (!groupedIssues[stateName]) {
              groupedIssues[stateName] = [];
            }
            groupedIssues[stateName].push(issue);
          });

          // 顯示分組的 Issues
          Object.keys(groupedIssues).forEach(stateName => {
            console.log(`\n📊 ${stateName}:`);
            groupedIssues[stateName].forEach(issue => {
              const priority = issue.priority ? ` (${issue.priority})` : '';
              const assignee = issue.assignee ? ` - ${issue.assignee.name}` : '';
              console.log(`  ${issue.identifier}: ${issue.title}${priority}${assignee}`);
              console.log(`    ID: ${issue.id}`);
              console.log(`    🔗 ${issue.url}`);
            });
          });
        }
        break;

      case 'states':
        console.log('🔍 正在取得可用狀態...');
        const statesResult = await getStates();
        
        if (statesResult.errors) {
          console.log('❌ API 錯誤:', statesResult.errors);
          return;
        }

        console.log('✅ 可用狀態:');
        if (statesResult.data && statesResult.data.workflowStates) {
          statesResult.data.workflowStates.nodes.forEach(state => {
            console.log(`  ${state.name} (ID: ${state.id}) - ${state.type}`);
          });
        }
        break;

      case 'update':
        if (args.length < 2) {
          console.log('❌ 請提供完整參數');
          console.log('用法: node linear-oauth-integration.cjs update <issue-id> <state-id>');
          return;
        }

        console.log(`🔄 正在更新 Issue ${args[0]} 狀態為 ${args[1]}...`);
        const updateResult = await updateIssueStatus(args[0], args[1]);
        
        if (updateResult.data && updateResult.data.issueUpdate.success) {
          console.log('✅ Issue 狀態更新成功！');
          console.log(`📋 ${updateResult.data.issueUpdate.issue.title} → ${updateResult.data.issueUpdate.issue.state.name}`);
        } else {
          console.log('❌ Issue 狀態更新失敗:', updateResult);
        }
        break;

      case 'edit':
        if (args.length < 3) {
          console.log('❌ 請提供完整參數');
          console.log('用法: node linear-oauth-integration.cjs edit <issue-id> <title> <description>');
          return;
        }

        console.log(`🔄 正在更新 Issue ${args[0]} 內容...`);
        const editResult = await updateIssueContent(args[0], args[1], args.slice(2).join(' '));
        
        if (editResult.data && editResult.data.issueUpdate.success) {
          console.log('✅ Issue 內容更新成功！');
          console.log(`📋 ${editResult.data.issueUpdate.issue.title}`);
        } else {
          console.log('❌ Issue 內容更新失敗:', editResult);
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

      case 'delete':
        if (args.length < 1) {
          console.log('❌ 請提供完整參數');
          console.log('用法: node linear-oauth-integration.cjs delete <issue-id>');
          return;
        }

        console.log(`🗑️ 正在刪除 Issue ${args[0]}...`);
        const deleteResult = await deleteIssue(args[0]);
        
        if (deleteResult.data && deleteResult.data.issueDelete.success) {
          console.log('✅ Issue 刪除成功！');
        } else {
          console.log('❌ Issue 刪除失敗:', deleteResult);
        }
        break;

      default:
        console.log('🏠 RentalRadar Linear 整合工具');
        console.log('\n可用指令:');
        console.log('  auth                    - 取得授權 URL');
        console.log('  token <授權碼>          - 使用授權碼取得 token');
        console.log('  status                  - 顯示 Teams 和 Projects');
        console.log('  list                    - 列出所有 Issues');
        console.log('  states                  - 列出可用狀態');
        console.log('  update <issue-id> <state-id> - 更新 Issue 狀態');
        console.log('  edit <issue-id> <title> <description> - 更新 Issue 內容');
        console.log('  create <team-id> <title> <description> - 建立 Issue');
        console.log('  delete <issue-id> - 刪除 Issue');
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
