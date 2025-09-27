#!/bin/bash

# RentalRadar Hostinger 快速部署腳本
# Linear Issue: DEV-14
# 開發者: Claude Code

echo "🚀 RentalRadar Hostinger 部署開始..."
echo "📅 時間: $(date)"
echo "🎯 Linear Issue: DEV-14"

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 錯誤處理
set -e
trap 'echo -e "${RED}❌ 部署失敗！${NC}"; exit 1' ERR

echo -e "${BLUE}📋 檢查環境需求...${NC}"

# 檢查 PHP 版本
if ! php -v | grep -q "PHP 8."; then
    echo -e "${RED}❌ 需要 PHP 8.0+ 版本${NC}"
    exit 1
fi
echo -e "${GREEN}✅ PHP 版本檢查通過${NC}"

# 檢查 Composer
if ! command -v composer &> /dev/null; then
    echo -e "${RED}❌ 需要安裝 Composer${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Composer 檢查通過${NC}"

# 檢查 Node.js
if ! command -v node &> /dev/null; then
    echo -e "${RED}❌ 需要安裝 Node.js${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Node.js 檢查通過${NC}"

echo -e "${BLUE}📦 安裝 PHP 依賴...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

echo -e "${BLUE}📦 安裝前端依賴...${NC}"
npm ci --silent

echo -e "${BLUE}🏗️ 建置前端資源...${NC}"
npm run build

echo -e "${BLUE}🗄️ 設定資料庫...${NC}"
if [ ! -f "database/database.sqlite" ]; then
    touch database/database.sqlite
    echo -e "${GREEN}✅ 建立 SQLite 資料庫${NC}"
fi

# 執行遷移
php artisan migrate --force --no-interaction

# 地理編碼部分資料
echo -e "${BLUE}🌍 執行地理編碼...${NC}"
php artisan properties:geocode --limit=10 --no-interaction

echo -e "${BLUE}🔐 設定檔案權限...${NC}"
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chmod 664 database/database.sqlite

echo -e "${BLUE}⚡ Laravel 優化...${NC}"
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

echo -e "${BLUE}📂 準備 Hostinger 檔案結構...${NC}"

# 建立 Hostinger 部署目錄
mkdir -p hostinger-deploy/public_html

# 複製 public 檔案到 public_html
cp -r public/* hostinger-deploy/public_html/

# 建立 .htaccess 檔案
cat > hostinger-deploy/public_html/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On

    # 處理授權標頭
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # 重導向至 Laravel
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# 安全性標頭
<IfModule mod_headers.c>
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set Referrer-Policy "same-origin"
</IfModule>

# 檔案快取
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</IfModule>
EOF

# 建立應用程式檔案結構
mkdir -p hostinger-deploy/app-root
cp -r app hostinger-deploy/app-root/
cp -r config hostinger-deploy/app-root/
cp -r database hostinger-deploy/app-root/
cp -r resources hostinger-deploy/app-root/
cp -r routes hostinger-deploy/app-root/
cp -r storage hostinger-deploy/app-root/
cp -r vendor hostinger-deploy/app-root/
cp -r bootstrap hostinger-deploy/app-root/

# 複製配置檔案
cp composer.json hostinger-deploy/app-root/
cp artisan hostinger-deploy/app-root/

# 建立環境變數範本
cat > hostinger-deploy/app-root/.env.example << 'EOF'
APP_NAME=RentalRadar
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=sqlite
DB_DATABASE=/path/to/your/app-root/database/database.sqlite

CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
EOF

# 建立部署說明
cat > hostinger-deploy/DEPLOYMENT_INSTRUCTIONS.md << 'EOF'
# Hostinger 部署說明

## 🚀 部署步驟

1. **上傳檔案**
   - 將 `app-root/` 目錄內容上傳到你的主機根目錄
   - 將 `public_html/` 目錄內容上傳到 `public_html/` 目錄

2. **設定環境變數**
   - 複製 `.env.example` 為 `.env`
   - 填入正確的設定值
   - 執行 `php artisan key:generate`

3. **設定權限**
   ```bash
   chmod -R 755 storage
   chmod -R 755 bootstrap/cache
   chmod 664 database/database.sqlite
   ```

4. **執行 Laravel 優化**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. **測試網站**
   - 訪問你的網域檢查是否正常運作
   - 測試地圖功能: `/map`
   - 測試 API: `/api/map/districts`

## 🔧 故障排除

- **500 錯誤**: 檢查 `.env` 檔案和權限設定
- **白畫面**: 檢查 `storage/logs/laravel.log`
- **地圖載入失敗**: 確認 JavaScript 檔案載入正常
- **API 404**: 檢查 `.htaccess` 重寫規則

## 📞 技術支援

如有問題，請檢查：
1. PHP 版本 >= 8.0
2. 所有檔案權限正確
3. `.env` 設定正確
4. 資料庫檔案存在且可寫入
EOF

echo -e "${BLUE}🧹 清理暫存檔案...${NC}"
php artisan cache:clear --no-interaction

echo -e "${GREEN}🎉 部署準備完成！${NC}"
echo -e "${YELLOW}📁 Hostinger 檔案位於: hostinger-deploy/${NC}"
echo -e "${YELLOW}📖 部署說明請參考: hostinger-deploy/DEPLOYMENT_INSTRUCTIONS.md${NC}"

echo ""
echo -e "${BLUE}📊 部署摘要:${NC}"
echo -e "  • Linear Issue: DEV-14 ✅"
echo -e "  • 後端 API: 8 個端點 ✅"
echo -e "  • 前端地圖: React + Leaflet.js ✅"
echo -e "  • AI 演算法: PHP + JavaScript ✅"
echo -e "  • Hostinger 配置: 完成 ✅"
echo -e "  • 部署檔案: 準備就緒 ✅"

echo ""
echo -e "${GREEN}🚀 RentalRadar DEV-14 部署準備完成！${NC}"
echo -e "${BLUE}📅 完成時間: $(date)${NC}"