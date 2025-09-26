<x-mail::message>
# 🏠 歡迎加入 RentalRadar！

親愛的 {{ $userName }}，

感謝您註冊 RentalRadar！我們很高興您選擇加入我們的租屋平台。

為了確保您的帳戶安全，請點擊下方按鈕來驗證您的電子郵件地址：

<x-mail::button :url="$verificationUrl" color="primary">
📧 驗證電子郵件地址
</x-mail::button>

## 🔒 安全提醒

- 此驗證連結將在 24 小時後過期
- 請勿將此連結分享給他人
- 如果您沒有註冊 RentalRadar 帳戶，請忽略此郵件

## 🚀 開始使用 RentalRadar

驗證完成後，您將可以：
- 瀏覽最新的租屋資訊
- 設定個人化的搜尋條件
- 收藏心儀的房源
- 與房東直接聯繫

---

**RentalRadar 團隊**  
讓租屋變得簡單、安全、透明

<x-mail::subcopy>
如果您無法點擊上方按鈕，請複製以下連結到瀏覽器中開啟：  
{{ $verificationUrl }}
</x-mail::subcopy>
</x-mail::message>