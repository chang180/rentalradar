import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            {/* 房屋圖標 */}
            <path
                d="M3 9L12 2L21 9V20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V9Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                fill="none"
            />
            {/* 雷達波紋 */}
            <circle
                cx="12"
                cy="12"
                r="3"
                stroke="currentColor"
                strokeWidth="1.5"
                fill="none"
                opacity="0.6"
            />
            <circle
                cx="12"
                cy="12"
                r="6"
                stroke="currentColor"
                strokeWidth="1"
                fill="none"
                opacity="0.4"
            />
            <circle
                cx="12"
                cy="12"
                r="9"
                stroke="currentColor"
                strokeWidth="0.5"
                fill="none"
                opacity="0.2"
            />
            {/* 中心點 */}
            <circle cx="12" cy="12" r="1" fill="currentColor" />
        </svg>
    );
}
