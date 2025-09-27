import { useCallback, useEffect, useState } from 'react';
import webSocketService, {
    ConnectionStatus,
} from '../services/WebSocketService';

export interface UseWebSocketReturn {
    connectionStatus: ConnectionStatus;
    isConnected: boolean;
    isReconnecting: boolean;
    lastConnected?: Date;
    error?: string;
    reconnect: () => void;
    disconnect: () => void;
    sendTestMessage: (message: string) => void;
}

export const useWebSocket = (): UseWebSocketReturn => {
    const [connectionStatus, setConnectionStatus] = useState<ConnectionStatus>(
        webSocketService.getConnectionStatus(),
    );

    // 監聽連接狀態變化
    useEffect(() => {
        const handleConnected = () => {
            setConnectionStatus(webSocketService.getConnectionStatus());
        };

        const handleDisconnected = () => {
            setConnectionStatus(webSocketService.getConnectionStatus());
        };

        const handleReconnecting = () => {
            setConnectionStatus(webSocketService.getConnectionStatus());
        };

        const handleError = (error: any) => {
            setConnectionStatus(webSocketService.getConnectionStatus());
        };

        webSocketService.on('connected', handleConnected);
        webSocketService.on('disconnected', handleDisconnected);
        webSocketService.on('reconnecting', handleReconnecting);
        webSocketService.on('error', handleError);

        return () => {
            webSocketService.off('connected', handleConnected);
            webSocketService.off('disconnected', handleDisconnected);
            webSocketService.off('reconnecting', handleReconnecting);
            webSocketService.off('error', handleError);
        };
    }, []);

    const reconnect = useCallback(() => {
        webSocketService.reconnect();
    }, []);

    const disconnect = useCallback(() => {
        webSocketService.disconnect();
    }, []);

    const sendTestMessage = useCallback((message: string) => {
        webSocketService.sendTestMessage(message);
    }, []);

    return {
        connectionStatus,
        isConnected: connectionStatus.connected,
        isReconnecting: connectionStatus.reconnecting,
        lastConnected: connectionStatus.lastConnected,
        error: connectionStatus.error,
        reconnect,
        disconnect,
        sendTestMessage,
    };
};
