# 本地端 Email 測試指南

## 🎯 本地端 Email 測試方案

### 方案 1: Log Driver (推薦)
**目前設定**: `MAIL_MAILER=log`

**優點**:
- ✅ 不需要額外設定
- ✅ Email 內容會記錄到 log 檔案
- ✅ 可以查看完整的 Email 內容
- ✅ 不會實際發送 Email

**查看 Email 內容**:
```bash
# 查看最新的 log 檔案
tail -f storage/logs/laravel.log

# 或者查看所有 log
cat storage/logs/laravel.log
```

### 方案 2: Mailtrap (可選)
**適合**: 需要更真實的 Email 測試體驗

**設定步驟**:
1. 註冊 Mailtrap 帳號 (免費)
2. 取得 SMTP 設定
3. 更新 .env 檔案:
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
```

### 方案 3: MailHog (本地端)
**適合**: 需要本地端 SMTP 伺服器

**安裝步驟**:
1. 下載 MailHog
2. 執行 MailHog
3. 設定 .env:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

## 🔧 當前設定確認

### .env 檔案設定
```env
MAIL_MAILER=log                    # 使用 log driver
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### config/mail.php 設定
```php
'default' => env('MAIL_MAILER', 'log'),  # 預設使用 log
```

## 📋 Email 驗證流程測試

### 1. 註冊新使用者
1. 訪問 `/register`
2. 填寫註冊表單
3. 提交註冊

### 2. 查看 Email 內容
```bash
# 查看 log 檔案中的 Email 內容
tail -f storage/logs/laravel.log | grep -A 20 -B 5 "Email"
```

### 3. 手動驗證 Email
1. 從 log 中複製驗證連結
2. 在瀏覽器中訪問驗證連結
3. 確認 Email 驗證成功

## 🚀 生產環境設定

### 部署時更新 .env
```env
# 生產環境 SMTP 設定
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@rentalradar.com"
MAIL_FROM_NAME="RentalRadar"
```

## 📝 測試檢查清單

### ✅ 本地端測試
- [ ] 註冊新使用者
- [ ] 檢查 log 檔案中的 Email 內容
- [ ] 點擊驗證連結
- [ ] 確認 Email 驗證成功
- [ ] 測試登入功能

### ✅ 生產環境測試
- [ ] 設定 SMTP 伺服器
- [ ] 測試 Email 發送
- [ ] 確認 Email 驗證流程
- [ ] 測試所有認證功能

## 💡 開發建議

### 本地端開發
- 使用 `log` driver 進行開發
- 定期檢查 log 檔案
- 使用 Mailtrap 進行更真實的測試

### 生產環境
- 使用可靠的 SMTP 服務
- 設定適當的 Email 範本
- 監控 Email 發送狀態

## 🔍 常見問題

### Q: 看不到 Email 內容？
**A**: 檢查 log 檔案路徑和權限
```bash
ls -la storage/logs/
```

### Q: Email 驗證連結無效？
**A**: 確保 APP_URL 設定正確
```env
APP_URL=http://rentalradar.test
```

### Q: 如何查看完整的 Email 內容？
**A**: 使用 grep 過濾 log 內容
```bash
grep -A 50 "Email" storage/logs/laravel.log
```
