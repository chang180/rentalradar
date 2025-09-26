// RentalRadar 專案管理腳本
// 不依賴 Linear API，用於本地專案管理

const fs = require('fs');
const path = require('path');

// 專案狀態管理
class ProjectManager {
  constructor() {
    this.projectFile = 'project-status.json';
    this.loadProject();
  }

  loadProject() {
    if (fs.existsSync(this.projectFile)) {
      this.project = JSON.parse(fs.readFileSync(this.projectFile, 'utf8'));
    } else {
      this.project = {
        name: 'RentalRadar',
        description: 'AI-Powered Rental Market Analytics Platform',
        phases: [
          {
            id: 'phase-1',
            name: '專案初始化',
            status: 'completed',
            issues: [
              {
                id: 'issue-1-1',
                title: 'Laravel 12 + React 專案建立',
                status: 'completed',
                description: '建立基礎專案結構'
              },
              {
                id: 'issue-1-2',
                title: '開發環境設定 (Herd)',
                status: 'completed',
                description: '設定 Laravel Herd 開發環境'
              },
              {
                id: 'issue-1-3',
                title: '使用者認證系統 (Fortify)',
                status: 'completed',
                description: '整合 Laravel Fortify 認證系統'
              }
            ]
          },
          {
            id: 'phase-2',
            name: 'AI 資料處理',
            status: 'in-progress',
            issues: [
              {
                id: 'issue-2-1',
                title: 'AI 資料清理演算法',
                status: 'in-progress',
                description: '開發智慧資料清理功能，處理政府開放資料的格式不一致問題',
                acceptanceCriteria: [
                  '能處理 CSV 和 XML 格式',
                  '自動檢測和修復資料格式錯誤',
                  '準確率 > 95%',
                  '處理速度 < 30秒 (10萬筆資料)'
                ]
              },
              {
                id: 'issue-2-2',
                title: '政府資料下載機制',
                status: 'pending',
                description: '自動下載政府開放資料平台的租賃實價登錄資料',
                acceptanceCriteria: [
                  '自動下載每月1、11、21日的資料',
                  '支援 CSV 和 XML 格式',
                  '錯誤重試機制',
                  '資料完整性驗證'
                ]
              }
            ]
          },
          {
            id: 'phase-3',
            name: 'AI 地圖系統',
            status: 'pending',
            issues: [
              {
                id: 'issue-3-1',
                title: 'Leaflet.js 地圖整合',
                status: 'pending',
                description: '建立基於 Leaflet.js 的地圖系統，支援租屋資料視覺化',
                acceptanceCriteria: [
                  '地圖載入速度 < 2秒',
                  '支援標記點顯示',
                  '響應式設計',
                  '支援縮放和平移'
                ]
              }
            ]
          }
        ]
      };
      this.saveProject();
    }
  }

  saveProject() {
    fs.writeFileSync(this.projectFile, JSON.stringify(this.project, null, 2));
  }

  // 顯示專案狀態
  showStatus() {
    console.log(`\n🏠 ${this.project.name}`);
    console.log(`📝 ${this.project.description}\n`);

    this.project.phases.forEach(phase => {
      const statusIcon = this.getStatusIcon(phase.status);
      console.log(`${statusIcon} ${phase.name} (${phase.status})`);
      
      phase.issues.forEach(issue => {
        const issueIcon = this.getStatusIcon(issue.status);
        console.log(`  ${issueIcon} ${issue.title}`);
      });
      console.log('');
    });
  }

  // 更新 Issue 狀態
  updateIssue(issueId, newStatus) {
    for (const phase of this.project.phases) {
      const issue = phase.issues.find(i => i.id === issueId);
      if (issue) {
        issue.status = newStatus;
        this.saveProject();
        console.log(`✅ 已更新 ${issue.title} 狀態為 ${newStatus}`);
        return;
      }
    }
    console.log(`❌ 找不到 Issue: ${issueId}`);
  }

  // 新增 Issue
  addIssue(phaseId, title, description, acceptanceCriteria = []) {
    const phase = this.project.phases.find(p => p.id === phaseId);
    if (phase) {
      const newIssue = {
        id: `issue-${Date.now()}`,
        title,
        description,
        status: 'pending',
        acceptanceCriteria
      };
      phase.issues.push(newIssue);
      this.saveProject();
      console.log(`✅ 已新增 Issue: ${title}`);
    } else {
      console.log(`❌ 找不到 Phase: ${phaseId}`);
    }
  }

  // 取得狀態圖示
  getStatusIcon(status) {
    const icons = {
      'completed': '✅',
      'in-progress': '🔄',
      'pending': '📋',
      'blocked': '🚫'
    };
    return icons[status] || '❓';
  }

  // 顯示詳細 Issue 資訊
  showIssue(issueId) {
    for (const phase of this.project.phases) {
      const issue = phase.issues.find(i => i.id === issueId);
      if (issue) {
        console.log(`\n📋 ${issue.title}`);
        console.log(`📝 ${issue.description}`);
        console.log(`📊 狀態: ${this.getStatusIcon(issue.status)} ${issue.status}`);
        
        if (issue.acceptanceCriteria && issue.acceptanceCriteria.length > 0) {
          console.log('\n🎯 驗收條件:');
          issue.acceptanceCriteria.forEach(criteria => {
            console.log(`  - ${criteria}`);
          });
        }
        return;
      }
    }
    console.log(`❌ 找不到 Issue: ${issueId}`);
  }
}

// 主程式
function main() {
  const manager = new ProjectManager();
  
  const command = process.argv[2];
  const args = process.argv.slice(3);

  switch (command) {
    case 'status':
      manager.showStatus();
      break;
    case 'update':
      if (args.length >= 2) {
        manager.updateIssue(args[0], args[1]);
      } else {
        console.log('用法: node project-manager.js update <issue-id> <new-status>');
      }
      break;
    case 'add':
      if (args.length >= 3) {
        manager.addIssue(args[0], args[1], args[2]);
      } else {
        console.log('用法: node project-manager.js add <phase-id> <title> <description>');
      }
      break;
    case 'show':
      if (args.length >= 1) {
        manager.showIssue(args[0]);
      } else {
        console.log('用法: node project-manager.js show <issue-id>');
      }
      break;
    default:
      console.log('🏠 RentalRadar 專案管理工具');
      console.log('\n可用指令:');
      console.log('  status                    - 顯示專案狀態');
      console.log('  update <issue-id> <status> - 更新 Issue 狀態');
      console.log('  add <phase-id> <title> <description> - 新增 Issue');
      console.log('  show <issue-id>          - 顯示 Issue 詳細資訊');
      console.log('\n範例:');
      console.log('  node project-manager.js status');
      console.log('  node project-manager.js update issue-2-1 completed');
      console.log('  node project-manager.js show issue-2-1');
  }
}

main();
