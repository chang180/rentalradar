/**
 * WebSocket 工具函數
 */

export interface WebSocketConfig {
    url: string;
    protocols?: string[];
    reconnectInterval?: number;
    maxReconnectAttempts?: number;
    heartbeatInterval?: number;
}

export interface WebSocketMessage {
    type: string;
    data: any;
    timestamp: number;
    id?: string;
}

export class WebSocketUtils {
    /**
     * 生成唯一 ID
     */
    static generateId(): string {
        return Math.random().toString(36).substr(2, 9);
    }

    /**
     * 格式化時間戳
     */
    static formatTimestamp(timestamp: number): string {
        return new Date(timestamp).toISOString();
    }

    /**
     * 檢查 WebSocket 支援
     */
    static isSupported(): boolean {
        return typeof WebSocket !== 'undefined';
    }

    /**
     * 檢查連接狀態
     */
    static isConnected(ws: WebSocket): boolean {
        return ws.readyState === WebSocket.OPEN;
    }

    /**
     * 等待連接建立
     */
    static waitForConnection(
        ws: WebSocket,
        timeout: number = 5000,
    ): Promise<boolean> {
        return new Promise((resolve) => {
            if (ws.readyState === WebSocket.OPEN) {
                resolve(true);
                return;
            }

            const timer = setTimeout(() => {
                resolve(false);
            }, timeout);

            ws.addEventListener(
                'open',
                () => {
                    clearTimeout(timer);
                    resolve(true);
                },
                { once: true },
            );
        });
    }

    /**
     * 安全發送訊息
     */
    static safeSend(ws: WebSocket, message: WebSocketMessage): boolean {
        if (!this.isConnected(ws)) {
            console.warn('WebSocket 未連接，無法發送訊息');
            return false;
        }

        try {
            ws.send(JSON.stringify(message));
            return true;
        } catch (error) {
            console.error('發送 WebSocket 訊息失敗:', error);
            return false;
        }
    }

    /**
     * 解析訊息
     */
    static parseMessage(data: string): WebSocketMessage | null {
        try {
            const parsed = JSON.parse(data);

            // 驗證訊息格式
            if (typeof parsed.type === 'string' && parsed.data !== undefined) {
                return {
                    type: parsed.type,
                    data: parsed.data,
                    timestamp: parsed.timestamp || Date.now(),
                    id: parsed.id,
                };
            }

            return null;
        } catch (error) {
            console.error('解析 WebSocket 訊息失敗:', error);
            return null;
        }
    }

    /**
     * 建立重連機制
     */
    static createReconnectHandler(
        createConnection: () => WebSocket,
        options: {
            interval?: number;
            maxAttempts?: number;
            onReconnect?: (attempt: number) => void;
            onMaxAttemptsReached?: () => void;
        } = {},
    ) {
        const {
            interval = 5000,
            maxAttempts = 5,
            onReconnect,
            onMaxAttemptsReached,
        } = options;

        let reconnectAttempts = 0;
        let reconnectTimer: NodeJS.Timeout | null = null;

        const attemptReconnect = () => {
            if (reconnectAttempts >= maxAttempts) {
                onMaxAttemptsReached?.();
                return;
            }

            reconnectAttempts++;
            onReconnect?.(reconnectAttempts);

            reconnectTimer = setTimeout(() => {
                const ws = createConnection();

                ws.addEventListener('open', () => {
                    reconnectAttempts = 0;
                    reconnectTimer = null;
                });

                ws.addEventListener('close', () => {
                    attemptReconnect();
                });
            }, interval);
        };

        return {
            start: attemptReconnect,
            stop: () => {
                if (reconnectTimer) {
                    clearTimeout(reconnectTimer);
                    reconnectTimer = null;
                }
            },
            reset: () => {
                reconnectAttempts = 0;
            },
        };
    }

    /**
     * 建立心跳機制
     */
    static createHeartbeat(
        ws: WebSocket,
        interval: number = 30000,
        message: WebSocketMessage = {
            type: 'ping',
            data: {},
            timestamp: Date.now(),
        },
    ) {
        const heartbeatTimer = setInterval(() => {
            if (this.isConnected(ws)) {
                this.safeSend(ws, message);
            }
        }, interval);

        return {
            stop: () => {
                clearInterval(heartbeatTimer);
            },
        };
    }

    /**
     * 防抖函數
     */
    static debounce<T extends (...args: any[]) => any>(
        func: T,
        wait: number,
    ): (...args: Parameters<T>) => void {
        let timeout: NodeJS.Timeout | null = null;

        return (...args: Parameters<T>) => {
            if (timeout) {
                clearTimeout(timeout);
            }

            timeout = setTimeout(() => {
                func(...args);
            }, wait);
        };
    }

    /**
     * 節流函數
     */
    static throttle<T extends (...args: any[]) => any>(
        func: T,
        limit: number,
    ): (...args: Parameters<T>) => void {
        let inThrottle: boolean = false;

        return (...args: Parameters<T>) => {
            if (!inThrottle) {
                func(...args);
                inThrottle = true;
                setTimeout(() => (inThrottle = false), limit);
            }
        };
    }

    /**
     * 壓縮資料
     */
    static compressData(data: any): string {
        // 簡單的資料壓縮，移除不必要的欄位
        const compressed = {
            type: data.type,
            data: data.data,
            timestamp: data.timestamp,
        };

        return JSON.stringify(compressed);
    }

    /**
     * 檢查訊息大小
     */
    static checkMessageSize(
        message: WebSocketMessage,
        maxSize: number = 1024 * 1024,
    ): boolean {
        const messageString = JSON.stringify(message);
        return messageString.length <= maxSize;
    }
}
