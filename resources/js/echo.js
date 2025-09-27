import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// 配置 Laravel Echo
window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'null', // 使用 null broadcaster 避免 Hostinger 相容性問題
    key: import.meta.env.VITE_PUSHER_APP_KEY || 'rentalradar',
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
    encrypted: true,
    logToConsole: false, // 關閉日誌避免錯誤
});

// 全域 Echo 實例
window.Echo = echo;

export default echo;
