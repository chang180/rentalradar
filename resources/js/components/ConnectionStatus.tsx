import React from 'react';
import { useWebSocket } from '../hooks/useWebSocket';

interface ConnectionStatusProps {
    showDetails?: boolean;
    className?: string;
}

export const ConnectionStatus: React.FC<ConnectionStatusProps> = ({
    showDetails = false,
    className = '',
}) => {
    const { connectionStatus, isConnected, isReconnecting, lastConnected, error } = useWebSocket();

    const getStatusColor = () => {
        if (isConnected) return 'text-green-600';
        if (isReconnecting) return 'text-yellow-600';
        return 'text-red-600';
    };

    const getStatusIcon = () => {
        if (isConnected) return '🟢';
        if (isReconnecting) return '🟡';
        return '🔴';
    };

    const getStatusText = () => {
        if (isConnected) return '已連接';
        if (isReconnecting) return '重新連接中...';
        return '已斷線';
    };

    const formatLastConnected = (date?: Date) => {
        if (!date) return '從未連接';
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const minutes = Math.floor(diff / 60000);
        
        if (minutes < 1) return '剛剛';
        if (minutes < 60) return `${minutes} 分鐘前`;
        
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} 小時前`;
        
        const days = Math.floor(hours / 24);
        return `${days} 天前`;
    };

    return (
        <div className={`flex items-center space-x-2 ${className}`}>
            <span className="text-sm">
                {getStatusIcon()}
            </span>
            <span className={`text-sm font-medium ${getStatusColor()}`}>
                {getStatusText()}
            </span>
            
            {showDetails && (
                <div className="text-xs text-gray-500">
                    {lastConnected && (
                        <div>
                            上次連接: {formatLastConnected(lastConnected)}
                        </div>
                    )}
                    {error && (
                        <div className="text-red-500">
                            錯誤: {error}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
