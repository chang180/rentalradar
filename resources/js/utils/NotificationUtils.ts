/**
 * 通知工具函數
 */

export interface NotificationOptions {
    id?: string;
    type?: 'success' | 'info' | 'warning' | 'error';
    title: string;
    message: string;
    duration?: number;
    autoClose?: boolean;
    position?: 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left';
    actions?: NotificationAction[];
}

export interface NotificationAction {
    label: string;
    action: () => void;
    style?: 'primary' | 'secondary' | 'danger';
}

export class NotificationUtils {
    /**
     * 生成通知 ID
     */
    static generateId(): string {
        return `notification-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * 格式化通知訊息
     */
    static formatMessage(message: string, variables: Record<string, any> = {}): string {
        return message.replace(/\{(\w+)\}/g, (match, key) => {
            return variables[key]?.toString() || match;
        });
    }

    /**
     * 獲取通知圖標
     */
    static getIcon(type: string): string {
        const icons = {
            success: '✅',
            info: 'ℹ️',
            warning: '⚠️',
            error: '❌',
        };
        return icons[type as keyof typeof icons] || 'ℹ️';
    }

    /**
     * 獲取通知顏色
     */
    static getColor(type: string): string {
        const colors = {
            success: 'text-green-600 bg-green-50 border-green-200',
            info: 'text-blue-600 bg-blue-50 border-blue-200',
            warning: 'text-yellow-600 bg-yellow-50 border-yellow-200',
            error: 'text-red-600 bg-red-50 border-red-200',
        };
        return colors[type as keyof typeof colors] || colors.info;
    }

    /**
     * 驗證通知選項
     */
    static validateOptions(options: NotificationOptions): NotificationOptions {
        return {
            id: options.id || this.generateId(),
            type: options.type || 'info',
            title: options.title || '通知',
            message: options.message || '',
            duration: options.duration || 5000,
            autoClose: options.autoClose !== false,
            position: options.position || 'top-right',
            actions: options.actions || [],
        };
    }

    /**
     * 建立系統通知
     */
    static createSystemNotification(options: NotificationOptions): Notification | null {
        if (!('Notification' in window)) {
            console.warn('此瀏覽器不支援系統通知');
            return null;
        }

        if (Notification.permission === 'denied') {
            console.warn('通知權限被拒絕');
            return null;
        }

        if (Notification.permission === 'default') {
            Notification.requestPermission().then((permission) => {
                if (permission === 'granted') {
                    this.createSystemNotification(options);
                }
            });
            return null;
        }

        const notification = new Notification(options.title, {
            body: options.message,
            icon: '/favicon.ico',
            tag: options.id,
        });

        if (options.autoClose && options.duration) {
            setTimeout(() => {
                notification.close();
            }, options.duration);
        }

        return notification;
    }

    /**
     * 請求通知權限
     */
    static async requestPermission(): Promise<NotificationPermission> {
        if (!('Notification' in window)) {
            return 'denied';
        }

        return await Notification.requestPermission();
    }

    /**
     * 檢查通知權限
     */
    static checkPermission(): NotificationPermission {
        if (!('Notification' in window)) {
            return 'denied';
        }

        return Notification.permission;
    }

    /**
     * 建立本地儲存通知
     */
    static saveToLocalStorage(notification: NotificationOptions): void {
        const notifications = this.getFromLocalStorage();
        notifications.push({
            ...notification,
            id: notification.id || this.generateId(),
            timestamp: Date.now(),
        });

        // 只保留最新的 50 個通知
        const recentNotifications = notifications
            .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0))
            .slice(0, 50);

        localStorage.setItem('notifications', JSON.stringify(recentNotifications));
    }

    /**
     * 從本地儲存獲取通知
     */
    static getFromLocalStorage(): (NotificationOptions & { timestamp?: number })[] {
        try {
            const stored = localStorage.getItem('notifications');
            return stored ? JSON.parse(stored) : [];
        } catch (error) {
            console.error('讀取本地通知失敗:', error);
            return [];
        }
    }

    /**
     * 清除本地儲存通知
     */
    static clearLocalStorage(): void {
        localStorage.removeItem('notifications');
    }

    /**
     * 建立通知群組
     */
    static createNotificationGroup(
        notifications: NotificationOptions[],
        groupTitle: string = '通知群組'
    ): NotificationOptions {
        return {
            id: this.generateId(),
            type: 'info',
            title: groupTitle,
            message: `收到 ${notifications.length} 個新通知`,
            duration: 10000,
            autoClose: true,
        };
    }

    /**
     * 過濾通知
     */
    static filterNotifications(
        notifications: NotificationOptions[],
        filter: {
            type?: string;
            since?: number;
            until?: number;
        }
    ): NotificationOptions[] {
        return notifications.filter(notification => {
            if (filter.type && notification.type !== filter.type) {
                return false;
            }

            if (filter.since && (notification as any).timestamp < filter.since) {
                return false;
            }

            if (filter.until && (notification as any).timestamp > filter.until) {
                return false;
            }

            return true;
        });
    }

    /**
     * 統計通知
     */
    static getNotificationStats(notifications: NotificationOptions[]): {
        total: number;
        byType: Record<string, number>;
        recent: number;
    } {
        const stats = {
            total: notifications.length,
            byType: {} as Record<string, number>,
            recent: 0,
        };

        const oneDayAgo = Date.now() - 24 * 60 * 60 * 1000;

        notifications.forEach(notification => {
            // 統計類型
            const type = notification.type || 'info';
            stats.byType[type] = (stats.byType[type] || 0) + 1;

            // 統計最近通知
            if ((notification as any).timestamp > oneDayAgo) {
                stats.recent++;
            }
        });

        return stats;
    }
}
