@echo off
echo 🐍 安裝 Python 依賴套件...

echo.
echo 1. 檢查 Python 是否已安裝...
python --version
if %errorlevel% neq 0 (
    echo ❌ Python 未安裝，請先安裝 Python 3.8+
    pause
    exit /b 1
)

echo.
echo 2. 安裝 Python 套件...
pip install -r requirements.txt

if %errorlevel% neq 0 (
    echo ❌ 套件安裝失敗
    pause
    exit /b 1
)

echo.
echo 3. 測試 Python 整合...
php test-python-integration.php

echo.
echo ✅ Python 依賴安裝完成！
pause
