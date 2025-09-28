// API 請求工具函數
export const apiRequest = async (url: string, options: RequestInit = {}) => {
    // 獲取 CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    const defaultHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
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
    return apiRequest(`/admin/api${endpoint}`, options);
};

// 專門用於檔案上傳的 API 請求函數
export const adminFileUploadRequest = async (endpoint: string, formData: FormData, options: RequestInit = {}) => {
    // 獲取 CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    const defaultHeaders = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
        // 不設置 Content-Type，讓瀏覽器自動設置為 multipart/form-data
    };

    const config: RequestInit = {
        method: 'POST',
        body: formData,
        headers: {
            ...defaultHeaders,
            ...options.headers,
        },
        credentials: 'same-origin',
        ...options,
    };

    const response = await fetch(`/admin/api${endpoint}`, config);
    
    if (!response.ok) {
        console.error('API 回應錯誤:', response.status, response.statusText);
        throw new Error(`API 請求失敗: ${response.status} ${response.statusText}`);
    }

    return response.json();
};
