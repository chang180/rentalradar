const https = require('https');

const LINEAR_API_KEY = 'key_f0d370ea684a4bcc29b3e99b0c41a8104526a4471c3259bfb5dbb01195d83089';

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

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': LINEAR_API_KEY
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

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': LINEAR_API_KEY
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
