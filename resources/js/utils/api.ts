// API 請求工具函數
export const apiRequest = async (url: string, options: RequestInit = {}) => {
    const defaultHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const config: RequestInit = {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers,
        },
        credentials: 'same-origin',
    };

    const response = await fetch(url, config);
    
    if (!response.ok) {
        console.error('API 回應錯誤:', response.status, response.statusText);
        throw new Error(`API 請求失敗: ${response.status} ${response.statusText}`);
    }

    return response.json();
};

// 專門用於管理員 API 的請求函數
export const adminApiRequest = async (endpoint: string, options: RequestInit = {}) => {
    return apiRequest(`/api/admin${endpoint}`, options);
};
