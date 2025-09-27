@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

if "%~2"=="" (
    echo 用法: create-issue.bat "標題" "描述"
    echo 範例: create-issue.bat "測試任務" "這是一個測試任務"
    exit /b 1
)

set "title=%~1"
set "description=%~2"

echo 🚀 正在建立 Issue...
echo 📋 標題: %title%
echo 📝 描述: %description%

node create-issue-safe.cjs "%title%" "%description%"
