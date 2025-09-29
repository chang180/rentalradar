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

    try {
        const response = await fetch(`/admin/api${endpoint}`, config);
        
        if (!response.ok) {
            console.error('API 回應錯誤:', response.status, response.statusText);
            
            // 針對特定錯誤提供更詳細的訊息
            if (response.status === 419) {
                throw new Error(`API 請求失敗: 419 - CSRF token 過期，請重新整理頁面`);
            } else if (response.status === 504) {
                throw new Error(`API 請求失敗: 504 - 伺服器處理超時，請稍後再試`);
            } else if (response.status === 413) {
                throw new Error(`API 請求失敗: 413 - 檔案太大，請選擇較小的檔案`);
            } else if (response.status === 403) {
                throw new Error(`API 請求失敗: 403 - 權限不足`);
            } else {
                throw new Error(`API 請求失敗: ${response.status} ${response.statusText}`);
            }
        }

        return response.json();
    } catch (error) {
        // 如果是網路錯誤或超時
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            throw new Error('網路連線錯誤，請檢查網路連線');
        }
        throw error;
    }
};
