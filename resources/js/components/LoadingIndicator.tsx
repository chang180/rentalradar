import React from 'react';

interface LoadingIndicatorProps {
    size?: 'sm' | 'md' | 'lg';
    color?: 'primary' | 'secondary' | 'success' | 'warning' | 'error';
    text?: string;
    className?: string;
    showProgress?: boolean;
}

export const LoadingIndicator: React.FC<LoadingIndicatorProps> = ({
    size = 'md',
    color = 'primary',
    text,
    className = '',
    showProgress = false,
}) => {
    const getSizeClasses = () => {
        switch (size) {
            case 'sm':
                return 'w-4 h-4';
            case 'lg':
                return 'w-8 h-8';
            case 'md':
            default:
                return 'w-6 h-6';
        }
    };

    const getColorClasses = () => {
        switch (color) {
            case 'secondary':
                return 'text-gray-600';
            case 'success':
                return 'text-green-600';
            case 'warning':
                return 'text-yellow-600';
            case 'error':
                return 'text-red-600';
            case 'primary':
            default:
                return 'text-blue-600';
        }
    };

    return (
        <div
            className={`flex flex-col items-center justify-center space-y-2 ${className}`}
        >
            <div className="relative">
                <div
                    className={`${getSizeClasses()} ${getColorClasses()} animate-spin`}
                    style={{
                        border: '2px solid transparent',
                        borderTop: '2px solid currentColor',
                        borderRadius: '50%',
                    }}
                />
                {showProgress && (
                    <div className="absolute inset-0">
                        <div
                            className={`${getSizeClasses()} animate-pulse border border-gray-200 dark:border-gray-600`}
                            style={{
                                borderRadius: '50%',
                            }}
                        />
                    </div>
                )}
            </div>
            {text && (
                <span
                    className={`text-sm ${getColorClasses()} ${showProgress ? 'animate-pulse' : ''}`}
                >
                    {text}
                </span>
            )}
        </div>
    );
};
