const https = require('https');
const fs = require('fs');
const path = require('path');

// 安全地從環境變數讀取 token
function getLinearToken() {
  // 首先檢查 process.env
  if (process.env.LINEAR_API_TOKEN) {
    return process.env.LINEAR_API_TOKEN;
  }

  // 如果環境變數不存在，嘗試從 .env 檔案讀取
  try {
    const envPath = path.join(__dirname, '../../.env');
    const envContent = fs.readFileSync(envPath, 'utf8');

    const lines = envContent.split('\n');
    for (const line of lines) {
      const trimmedLine = line.trim();
      if (trimmedLine.startsWith('LINEAR_API_TOKEN=')) {
        return trimmedLine.split('=')[1].trim();
      }
    }
  } catch (error) {
    console.log('⚠️  無法讀取 LINEAR_API_TOKEN 環境變數:', error.message);
  }

  return null;
}
const LINEAR_API_URL = 'https://api.linear.app/graphql';

// 進行 API 請求的函數
async function makeApiRequest(query, variables = {}) {
  const token = getLinearToken();
  if (!token) {
    throw new Error('LINEAR_API_TOKEN 不存在於環境變數中');
  }

  const options = {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
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

// 測試 API 連接
async function testConnection() {
  console.log('🔍 測試 Linear API 連接...');

  try {
    // 測試基本的 viewer query
    const query = `
      query {
        viewer {
          id
          name
          email
        }
      }
    `;

    const result = await makeApiRequest(query);

    if (result.errors) {
      console.log('❌ API 錯誤:', result.errors);
      return false;
    }

    if (result.data && result.data.viewer) {
      console.log('✅ Linear API 連接成功！');
      console.log('👤 使用者資訊:', result.data.viewer);
      return true;
    }

    console.log('⚠️  意外的回應:', result);
    return false;

  } catch (error) {
    console.log('❌ 連接錯誤:', error.message);
    return false;
  }
}

// 列出團隊和專案
async function listTeamsAndProjects() {
  console.log('🔍 正在取得團隊和專案...');

  try {
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

    const result = await makeApiRequest(query);

    if (result.errors) {
      console.log('❌ API 錯誤:', result.errors);
      return;
    }

    if (result.data && result.data.teams) {
      console.log('✅ 團隊和專案資料取得成功！');

      result.data.teams.nodes.forEach(team => {
        console.log(`\n🏢 團隊: ${team.name} (ID: ${team.id})`);
        if (team.projects && team.projects.nodes.length > 0) {
          team.projects.nodes.forEach(project => {
            console.log(`  📁 專案: ${project.name} (ID: ${project.id})`);
            console.log(`     描述: ${project.description || '無描述'}`);
            console.log(`     狀態: ${project.state}`);
          });
        } else {
          console.log('  📁 無專案');
        }
      });
    }

  } catch (error) {
    console.log('❌ 錯誤:', error.message);
  }
}

// 主要執行函數
async function main() {
  // 檢查環境變數是否可取得
  const testToken = getLinearToken();
  if (!testToken) {
    console.log('❌ 無法取得 LINEAR_API_TOKEN 環境變數');
    console.log('💡 請確認 .env 檔案中有設定 LINEAR_API_TOKEN 或在環境變數中設定');
    return;
  }

  console.log('✅ LINEAR_API_TOKEN 環境變數取得成功');

  const success = await testConnection();

  if (success) {
    await listTeamsAndProjects();
  }
}

main().catch(console.error);