import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAdminCheck } from '@/hooks/useAdmin';
import { Head } from '@inertiajs/react';
import { 
    Shield, 
    ShieldCheck, 
    User, 
    Upload, 
    Calendar,
    BarChart3,
    Settings,
    RefreshCw
} from 'lucide-react';
import { useState, useEffect } from 'react';

interface UserPermissions {
    user_id: number;
    name: string;
    email: string;
    permissions: {
        is_admin: boolean;
        can_upload: boolean;
        can_manage_schedules: boolean;
        can_manage_users: boolean;
        can_view_performance: boolean;
    };
}

export default function AdminPermissions() {
    const isAdmin = useAdminCheck();
    const [permissions, setPermissions] = useState<UserPermissions | null>(null);
    const [loading, setLoading] = useState(true);

    // 載入使用者權限
    const loadPermissions = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/admin/permissions');
            if (response.ok) {
                const data = await response.json();
                setPermissions(data.data);
            }
        } catch (error) {
            console.error('載入權限資訊失敗:', error);
        } finally {
            setLoading(false);
        }
    };

    // 清除權限快取
    const clearPermissionCache = async () => {
        try {
            const response = await fetch('/api/admin/clear-cache', {
                method: 'POST',
            });

            if (response.ok) {
                alert('權限快取已清除！');
                loadPermissions(); // 重新載入權限
            } else {
                const error = await response.json();
                alert(`清除快取失敗: ${error.message}`);
            }
        } catch (error) {
            console.error('清除權限快取失敗:', error);
            alert('清除權限快取失敗');
        }
    };

    useEffect(() => {
        if (isAdmin) {
            loadPermissions();
        }
    }, [isAdmin]);

    if (!isAdmin) {
        return (
            <div className="container mx-auto py-8">
                <div className="text-center">
                    <Shield className="mx-auto h-12 w-12 text-gray-400" />
                    <h2 className="mt-4 text-lg font-semibold text-gray-900">
                        權限不足
                    </h2>
                    <p className="mt-2 text-gray-600">
                        您沒有權限存取此頁面。
                    </p>
                </div>
            </div>
        );
    }

    const permissionItems = [
        {
            key: 'is_admin',
            title: '管理員權限',
            description: '完整的系統管理權限',
            icon: ShieldCheck,
            color: 'text-purple-600',
            bgColor: 'bg-purple-50',
        },
        {
            key: 'can_upload',
            title: '檔案上傳權限',
            description: '上傳政府資料檔案',
            icon: Upload,
            color: 'text-blue-600',
            bgColor: 'bg-blue-50',
        },
        {
            key: 'can_manage_schedules',
            title: '排程管理權限',
            description: '管理系統排程任務',
            icon: Calendar,
            color: 'text-green-600',
            bgColor: 'bg-green-50',
        },
        {
            key: 'can_manage_users',
            title: '使用者管理權限',
            description: '管理系統使用者帳號',
            icon: User,
            color: 'text-orange-600',
            bgColor: 'bg-orange-50',
        },
        {
            key: 'can_view_performance',
            title: '效能監控權限',
            description: '查看系統效能監控',
            icon: BarChart3,
            color: 'text-red-600',
            bgColor: 'bg-red-50',
        },
    ];

    return (
        <>
            <Head title="權限管理" />

            <div className="container mx-auto py-8">
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">權限管理</h1>
                        <p className="mt-2 text-gray-600">
                            查看和管理系統權限設定
                        </p>
                    </div>
                    <Button onClick={clearPermissionCache} variant="outline">
                        <RefreshCw className="h-4 w-4 mr-2" />
                        清除快取
                    </Button>
                </div>

                {loading ? (
                    <div className="text-center py-8">
                        <div className="text-gray-500">載入中...</div>
                    </div>
                ) : permissions ? (
                    <div className="space-y-6">
                        {/* 使用者資訊 */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    使用者資訊
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-gray-700">姓名</label>
                                        <p className="mt-1 text-sm text-gray-900">{permissions.name}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-700">電子郵件</label>
                                        <p className="mt-1 text-sm text-gray-900">{permissions.email}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* 權限列表 */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    權限狀態
                                </CardTitle>
                                <CardDescription>
                                    您目前擁有的系統權限
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {permissionItems.map((item) => {
                                        const hasPermission = permissions.permissions[item.key as keyof typeof permissions.permissions];
                                        const IconComponent = item.icon;
                                        
                                        return (
                                            <div
                                                key={item.key}
                                                className={`rounded-lg border p-4 ${
                                                    hasPermission 
                                                        ? `${item.bgColor} border-green-200` 
                                                        : 'bg-gray-50 border-gray-200'
                                                }`}
                                            >
                                                <div className="flex items-start gap-3">
                                                    <div className={`flex-shrink-0 ${hasPermission ? item.color : 'text-gray-400'}`}>
                                                        <IconComponent className="h-6 w-6" />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <h3 className={`text-sm font-medium ${
                                                            hasPermission ? 'text-gray-900' : 'text-gray-500'
                                                        }`}>
                                                            {item.title}
                                                        </h3>
                                                        <p className={`mt-1 text-xs ${
                                                            hasPermission ? 'text-gray-600' : 'text-gray-400'
                                                        }`}>
                                                            {item.description}
                                                        </p>
                                                        <div className="mt-2">
                                                            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                                hasPermission
                                                                    ? 'bg-green-100 text-green-800'
                                                                    : 'bg-gray-100 text-gray-800'
                                                            }`}>
                                                                {hasPermission ? '已授權' : '未授權'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>

                        {/* 權限說明 */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Settings className="h-5 w-5" />
                                    權限說明
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="prose prose-sm max-w-none">
                                    <h4>權限層級說明：</h4>
                                    <ul className="list-disc pl-6 space-y-2">
                                        <li>
                                            <strong>管理員權限</strong>：擁有完整的系統管理權限，包含所有其他權限
                                        </li>
                                        <li>
                                            <strong>檔案上傳權限</strong>：可以上傳政府資料檔案並進行資料匯入
                                        </li>
                                        <li>
                                            <strong>排程管理權限</strong>：可以設定和管理系統自動化任務排程
                                        </li>
                                        <li>
                                            <strong>使用者管理權限</strong>：可以管理系統使用者帳號和權限
                                        </li>
                                        <li>
                                            <strong>效能監控權限</strong>：可以查看系統效能監控和分析報告
                                        </li>
                                    </ul>
                                    
                                    <h4 className="mt-6">注意事項：</h4>
                                    <ul className="list-disc pl-6 space-y-2">
                                        <li>所有權限都基於管理員身份 (is_admin) 進行控制</li>
                                        <li>權限資訊會快取 10 分鐘以提升效能</li>
                                        <li>權限變更會自動清除相關快取</li>
                                        <li>如需修改權限，請聯絡系統管理員</li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    <div className="text-center py-8">
                        <Shield className="mx-auto h-12 w-12 text-gray-400" />
                        <h3 className="mt-4 text-lg font-semibold text-gray-900">
                            無法載入權限資訊
                        </h3>
                        <p className="mt-2 text-gray-600">
                            請重新整理頁面或聯絡系統管理員
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
