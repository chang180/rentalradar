import echo from '../echo';
import { EventEmitter } from '../utils/EventEmitter';

export interface WebSocketMessage {
    type: 'map_update' | 'notification' | 'system_status';
    data: any;
    timestamp: number;
}

export interface ConnectionStatus {
    connected: boolean;
    reconnecting: boolean;
    lastConnected?: Date;
    error?: string;
}

export class WebSocketService extends EventEmitter {
    private echo: any;
    private connectionStatus: ConnectionStatus = {
        connected: false,
        reconnecting: false,
    };

    constructor() {
        super();
        this.echo = echo;
        this.setupEventListeners();
    }

    /**
     * 設置事件監聽器
     */
    private setupEventListeners(): void {
        // 監聽地圖更新
        this.echo.channel('map-updates')
            .listen('MapDataUpdated', (data: any) => {
                this.emit('mapUpdate', data);
            });

        // 監聽通知
        this.echo.channel('notifications')
            .listen('RealTimeNotification', (data: any) => {
                this.emit('notification', data);
            });

        // 監聽系統狀態
        this.echo.channel('system-status')
            .listen('SystemStatus', (data: any) => {
                this.emit('systemStatus', data);
            });

        // 連接狀態監聽 - 檢查 Echo 是否有有效的 connector
        if (this.echo.connector && this.echo.connector.pusher && this.echo.connector.pusher.connection) {
            this.echo.connector.pusher.connection.bind('connected', () => {
                this.connectionStatus.connected = true;
                this.connectionStatus.reconnecting = false;
                this.connectionStatus.lastConnected = new Date();
                this.emit('connected');
            });

            this.echo.connector.pusher.connection.bind('disconnected', () => {
                this.connectionStatus.connected = false;
                this.emit('disconnected');
            });

            this.echo.connector.pusher.connection.bind('reconnecting', () => {
                this.connectionStatus.reconnecting = true;
                this.emit('reconnecting');
            });
        } else {
            // 如果沒有有效的 connector，模擬連接狀態
            console.warn('WebSocketService: Echo connector not available, using mock connection status');
            this.connectionStatus.connected = true;
            this.connectionStatus.lastConnected = new Date();
            this.emit('connected');
        }

        // 錯誤處理也需要檢查 connector 是否存在
        if (this.echo.connector && this.echo.connector.pusher && this.echo.connector.pusher.connection) {
            this.echo.connector.pusher.connection.bind('error', (error: any) => {
                this.connectionStatus.error = error.message;
                this.emit('error', error);
            });
        }
    }

    /**
     * 訂閱用戶特定頻道
     */
    subscribeToUserChannel(userId: string): void {
        this.echo.private(`user.${userId}`)
            .listen('RealTimeNotification', (data: any) => {
                this.emit('userNotification', data);
            });
    }

    /**
     * 取消訂閱用戶頻道
     */
    unsubscribeFromUserChannel(userId: string): void {
        this.echo.leave(`user.${userId}`);
    }

    /**
     * 訂閱地圖更新頻道
     */
    subscribeToMapUpdates(): void {
        this.echo.channel('map-updates')
            .listen('MapDataUpdated', (data: any) => {
                this.emit('mapUpdate', data);
            });
    }

    /**
     * 取消訂閱地圖更新
     */
    unsubscribeFromMapUpdates(): void {
        this.echo.leave('map-updates');
    }

    /**
     * 獲取連接狀態
     */
    getConnectionStatus(): ConnectionStatus {
        return { ...this.connectionStatus };
    }

    /**
     * 手動重連
     */
    reconnect(): void {
        this.echo.disconnect();
        this.echo.connect();
    }

    /**
     * 斷開連接
     */
    disconnect(): void {
        this.echo.disconnect();
        this.connectionStatus.connected = false;
    }

    /**
     * 發送測試訊息
     */
    sendTestMessage(message: string): void {
        this.emit('testMessage', { message, timestamp: Date.now() });
    }
}

// 單例模式
export const webSocketService = new WebSocketService();
export default webSocketService;
