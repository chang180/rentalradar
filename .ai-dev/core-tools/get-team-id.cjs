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

// 獲取團隊資訊
async function getTeams() {
  const query = `
    query {
      teams {
        nodes {
          id
          name
          key
        }
      }
    }
  `;

  return await makeApiRequest(query);
}

// 主要功能
async function main() {
  try {
    console.log('🔍 正在取得團隊資訊...');
    const result = await getTeams();
    
    if (result.errors) {
      console.log('❌ API 錯誤:', result.errors);
      return;
    }

    console.log('✅ 團隊資訊:');
    if (result.data && result.data.teams) {
      result.data.teams.nodes.forEach(team => {
        console.log(`  ${team.name} (ID: ${team.id}) - Key: ${team.key}`);
      });
    }

  } catch (error) {
    console.error('❌ 錯誤:', error.message);
  }
}

main();
