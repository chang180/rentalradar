# PowerShell 腳本 - 處理中文 Issue 建立
param(
    [Parameter(Mandatory=$true)]
    [string]$Title,
    
    [Parameter(Mandatory=$true)]
    [string]$Description
)

# 設定編碼
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "🚀 正在建立 Issue..." -ForegroundColor Green
Write-Host "📋 標題: $Title" -ForegroundColor Cyan
Write-Host "📝 描述: $Description" -ForegroundColor Cyan

# 使用 Node.js 腳本建立 Issue
node create-issue-safe.cjs $Title $Description
