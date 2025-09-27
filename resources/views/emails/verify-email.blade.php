<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>驗證您的電子郵件地址 - RentalRadar</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .button { background-color: #2d3748; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏠 歡迎加入 RentalRadar！</h1>
        </div>
        
        <p>親愛的 {{ $userName }}，</p>
        
        <p>感謝您註冊 RentalRadar！我們很高興您選擇加入我們的租屋平台。</p>
        
        <p>為了確保您的帳戶安全，請點擊下方按鈕來驗證您的電子郵件地址：</p>
        
        <p style="text-align: center;">
            <a href="{!! $verificationUrl !!}" class="button">📧 驗證電子郵件地址</a>
        </p>
        
        <h2>🔒 安全提醒</h2>
        <ul>
            <li>此驗證連結將在 24 小時後過期</li>
            <li>請勿將此連結分享給他人</li>
            <li>如果您沒有註冊 RentalRadar 帳戶，請忽略此郵件</li>
        </ul>
        
        <h2>🚀 開始使用 RentalRadar</h2>
        <p>驗證完成後，您將可以：</p>
        <ul>
            <li>瀏覽最新的租屋資訊</li>
            <li>設定個人化的搜尋條件</li>
            <li>收藏心儀的房源</li>
            <li>與房東直接聯繫</li>
        </ul>
        
        <div class="footer">
            <p><strong>RentalRadar 團隊</strong><br>
            讓租屋變得簡單、安全、透明</p>
            
            <p>如果您無法點擊上方按鈕，請複製以下連結到瀏覽器中開啟：<br>
            <a href="{!! $verificationUrl !!}">{!! $verificationUrl !!}</a></p>
        </div>
    </div>
</body>
</html>