import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// 配置 Laravel Echo
window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'log', // 使用 log driver 確保 Hostinger 相容性
    key: process.env.MIX_PUSHER_APP_KEY || 'rentalradar',
    cluster: process.env.MIX_PUSHER_APP_CLUSTER || 'mt1',
    encrypted: true,
    logToConsole: true, // 開發環境啟用日誌
});

// 全域 Echo 實例
window.Echo = echo;

export default echo;
