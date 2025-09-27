import React, { useState, useEffect } from 'react';
import webSocketService from '../services/WebSocketService';

interface Notification {
    id: string;
    type: 'success' | 'info' | 'warning' | 'error';
    title: string;
    message: string;
    timestamp: number;
    autoClose?: boolean;
    duration?: number;
}

interface RealtimeNotificationsProps {
    position?: 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left';
    maxNotifications?: number;
    autoClose?: boolean;
    defaultDuration?: number;
}

export const RealtimeNotifications: React.FC<RealtimeNotificationsProps> = ({
    position = 'top-right',
    maxNotifications = 5,
    autoClose = true,
    defaultDuration = 5000,
}) => {
    const [notifications, setNotifications] = useState<Notification[]>([]);

    useEffect(() => {
        const handleNotification = (data: any) => {
            const notification: Notification = {
                id: data.id || `notification-${Date.now()}`,
                type: data.type || 'info',
                title: data.title || '通知',
                message: data.message || data.content || '',
                timestamp: data.timestamp || Date.now(),
                autoClose: data.autoClose !== undefined ? data.autoClose : autoClose,
                duration: data.duration || defaultDuration,
            };

            setNotifications(prev => {
                const newNotifications = [notification, ...prev].slice(0, maxNotifications);
                return newNotifications;
            });

            // 自動關閉
            if (notification.autoClose) {
                setTimeout(() => {
                    removeNotification(notification.id);
                }, notification.duration);
            }
        };

        const handleUserNotification = (data: any) => {
            handleNotification({ ...data, type: 'info' });
        };

        webSocketService.on('notification', handleNotification);
        webSocketService.on('userNotification', handleUserNotification);

        return () => {
            webSocketService.off('notification', handleNotification);
            webSocketService.off('userNotification', handleUserNotification);
        };
    }, [maxNotifications, autoClose, defaultDuration]);

    const removeNotification = (id: string) => {
        setNotifications(prev => prev.filter(notification => notification.id !== id));
    };

    const getNotificationIcon = (type: string) => {
        switch (type) {
            case 'success':
                return '✅';
            case 'error':
                return '❌';
            case 'warning':
                return '⚠️';
            case 'info':
            default:
                return 'ℹ️';
        }
    };

    const getNotificationStyles = (type: string) => {
        const baseStyles = 'p-4 rounded-lg shadow-lg border-l-4 mb-2 transition-all duration-300';
        
        switch (type) {
            case 'success':
                return `${baseStyles} bg-green-50 border-green-500 text-green-800`;
            case 'error':
                return `${baseStyles} bg-red-50 border-red-500 text-red-800`;
            case 'warning':
                return `${baseStyles} bg-yellow-50 border-yellow-500 text-yellow-800`;
            case 'info':
            default:
                return `${baseStyles} bg-blue-50 border-blue-500 text-blue-800`;
        }
    };

    const getPositionStyles = () => {
        switch (position) {
            case 'top-left':
                return 'fixed top-4 left-4 z-50';
            case 'bottom-right':
                return 'fixed bottom-4 right-4 z-50';
            case 'bottom-left':
                return 'fixed bottom-4 left-4 z-50';
            case 'top-right':
            default:
                return 'fixed top-4 right-4 z-50';
        }
    };

    if (notifications.length === 0) {
        return null;
    }

    return (
        <div className={getPositionStyles()}>
            <div className="space-y-2">
                {notifications.map(notification => (
                    <div
                        key={notification.id}
                        className={getNotificationStyles(notification.type)}
                        style={{
                            animation: 'slideIn 0.3s ease-out',
                        }}
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex items-start space-x-3">
                                <span className="text-lg">
                                    {getNotificationIcon(notification.type)}
                                </span>
                                <div className="flex-1">
                                    <h4 className="font-semibold text-sm">
                                        {notification.title}
                                    </h4>
                                    <p className="text-sm mt-1">
                                        {notification.message}
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        {new Date(notification.timestamp).toLocaleTimeString()}
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => removeNotification(notification.id)}
                                className="ml-2 text-gray-400 hover:text-gray-600 transition-colors"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
