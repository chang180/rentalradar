#!/usr/bin/env node

const { execSync } = require('child_process');
const path = require('path');

// 工具路徑
const TOOLS_DIR = __dirname;

// 可用指令
const commands = {
  'auth': 'linear-oauth-integration.cjs auth',
  'token': 'linear-oauth-integration.cjs token',
  'status': 'linear-oauth-integration.cjs status',
  'list': 'linear-issues.cjs list',
  'states': 'linear-issues.cjs states',
  'update': 'linear-issues.cjs update',
  'project': 'project-manager.cjs',
  'complete': 'complete-linear-tasks.cjs complete'
};

// 主要功能
function main() {
  const args = process.argv.slice(2);
  
  if (args.length === 0) {
    showHelp();
    return;
  }

  const command = args[0];
  const remainingArgs = args.slice(1);

  if (commands[command]) {
    const toolPath = path.join(TOOLS_DIR, commands[command]);
    const fullCommand = `node "${toolPath}" ${remainingArgs.join(' ')}`;
    
    try {
      console.log(`🚀 執行: ${fullCommand}\n`);
      execSync(fullCommand, { stdio: 'inherit', cwd: process.cwd() });
    } catch (error) {
      console.error('❌ 執行失敗:', error.message);
    }
  } else {
    console.log(`❌ 未知指令: ${command}`);
    showHelp();
  }
}

// 顯示幫助
function showHelp() {
  console.log('🏠 RentalRadar Linear 整合工具');
  console.log('\n可用指令:');
  console.log('  auth                    - 取得 OAuth 授權 URL');
  console.log('  token <授權碼>         - 使用授權碼取得 token');
  console.log('  status                  - 查看 Linear 連接狀態');
  console.log('  list                    - 列出所有 Issues');
  console.log('  states                  - 列出可用狀態');
  console.log('  update <issue-id> <state-id> - 更新 Issue 狀態');
  console.log('  project <command>       - 本地專案管理');
  console.log('  complete                - 完成所有 Linear 任務');
  console.log('\n範例:');
  console.log('  node .ai-dev/linear-integration/linear-cli.cjs auth');
  console.log('  node .ai-dev/linear-integration/linear-cli.cjs list');
  console.log('  node .ai-dev/linear-integration/linear-cli.cjs project status');
}

main();
