const https = require('https');
const fs = require('fs');
const path = require('path');

// 讀取儲存的 token
function loadToken() {
  try {
    // 優先使用上層目錄的 token 檔案
    const parentTokenPath = path.join(__dirname, '../.ai-dev-tools/linear-token.json');
    const localTokenPath = path.join(__dirname, 'linear-token.json');
    
    if (fs.existsSync(parentTokenPath)) {
      return JSON.parse(fs.readFileSync(parentTokenPath, 'utf8'));
    } else if (fs.existsSync(localTokenPath)) {
      return JSON.parse(fs.readFileSync(localTokenPath, 'utf8'));
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

// 列出所有 Issues
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

// 主要功能
async function main() {
  const command = process.argv[2];
  const args = process.argv.slice(3);

  try {
    switch (command) {
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
          console.log('用法: node linear-issues.cjs update <issue-id> <state-id>');
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

      default:
        console.log('🏠 Linear Issues 管理工具');
        console.log('\n可用指令:');
        console.log('  list                    - 列出所有 Issues');
        console.log('  states                  - 列出可用狀態');
        console.log('  update <issue-id> <state-id> - 更新 Issue 狀態');
        console.log('\n範例:');
        console.log('  node linear-issues.cjs list');
        console.log('  node linear-issues.cjs states');
        console.log('  node linear-issues.cjs update DEV-5 40b1bdfd-2caa-4306-9fc4-8c4f2d646cec');
    }

  } catch (error) {
    console.error('❌ 錯誤:', error.message);
  }
}

main();

