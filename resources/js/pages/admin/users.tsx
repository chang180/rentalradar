import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAdminCheck } from '@/hooks/useAdmin';
import { Head, router } from '@inertiajs/react';
import { 
    Plus, 
    Search, 
    Shield, 
    ShieldCheck, 
    Trash2, 
    User, 
    Users 
} from 'lucide-react';
import { useState, useEffect } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
}

interface UsersResponse {
    users: User[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export default function AdminUsers() {
    const isAdmin = useAdminCheck();
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [role, setRole] = useState<string>('all');
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    // 載入使用者列表
    const loadUsers = async (page = 1, searchTerm = '', roleFilter = 'all') => {
        try {
            setLoading(true);
            const params = new URLSearchParams({
                page: page.toString(),
                per_page: '15',
                ...(searchTerm && { search: searchTerm }),
                ...(roleFilter !== 'all' && { role: roleFilter }),
            });

            const response = await fetch(`/api/admin/users?${params}`);
            if (response.ok) {
                const data: UsersResponse = await response.json();
                setUsers(data.users);
                setPagination(data.pagination);
            }
        } catch (error) {
            console.error('載入使用者列表失敗:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (isAdmin) {
            loadUsers(1, search, role);
        }
    }, [isAdmin, search, role]);

    // 提升使用者為管理員
    const promoteUser = async (userId: number) => {
        try {
            const response = await fetch(`/api/admin/users/${userId}/promote`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                // 重新載入使用者列表
                loadUsers(pagination.current_page, search, role);
            } else {
                const error = await response.json();
                alert(`提升失敗: ${error.message}`);
            }
        } catch (error) {
            console.error('提升使用者失敗:', error);
            alert('提升使用者失敗');
        }
    };

    // 撤銷管理員權限
    const demoteUser = async (userId: number) => {
        try {
            const response = await fetch(`/api/admin/users/${userId}/demote`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                // 重新載入使用者列表
                loadUsers(pagination.current_page, search, role);
            } else {
                const error = await response.json();
                alert(`撤銷失敗: ${error.message}`);
            }
        } catch (error) {
            console.error('撤銷管理員權限失敗:', error);
            alert('撤銷管理員權限失敗');
        }
    };

    // 刪除使用者
    const deleteUser = async (userId: number, userName: string) => {
        if (!confirm(`確定要刪除使用者 "${userName}" 嗎？此操作無法復原。`)) {
            return;
        }

        try {
            const response = await fetch(`/api/admin/users/${userId}`, {
                method: 'DELETE',
            });

            if (response.ok) {
                // 重新載入使用者列表
                loadUsers(pagination.current_page, search, role);
            } else {
                const error = await response.json();
                alert(`刪除失敗: ${error.message}`);
            }
        } catch (error) {
            console.error('刪除使用者失敗:', error);
            alert('刪除使用者失敗');
        }
    };

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

    return (
        <>
            <Head title="使用者管理" />

            <div className="container mx-auto py-8">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900">使用者管理</h1>
                    <p className="mt-2 text-gray-600">
                        管理系統使用者帳號和權限
                    </p>
                </div>

                {/* 統計卡片 */}
                <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">總使用者數</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{pagination.total}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">管理員數量</CardTitle>
                            <ShieldCheck className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {users.filter(user => user.is_admin).length}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">一般使用者</CardTitle>
                            <User className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {users.filter(user => !user.is_admin).length}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* 搜尋和篩選 */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>搜尋和篩選</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-4 md:flex-row">
                            <div className="flex-1">
                                <Label htmlFor="search">搜尋使用者</Label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <Input
                                        id="search"
                                        placeholder="輸入姓名或電子郵件..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            <div className="w-full md:w-48">
                                <Label htmlFor="role">角色篩選</Label>
                                <Select value={role} onValueChange={setRole}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="選擇角色" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">全部</SelectItem>
                                        <SelectItem value="admin">管理員</SelectItem>
                                        <SelectItem value="user">一般使用者</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 使用者列表 */}
                <Card>
                    <CardHeader>
                        <CardTitle>使用者列表</CardTitle>
                        <CardDescription>
                            共 {pagination.total} 位使用者
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="text-center py-8">
                                <div className="text-gray-500">載入中...</div>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>姓名</TableHead>
                                        <TableHead>電子郵件</TableHead>
                                        <TableHead>角色</TableHead>
                                        <TableHead>註冊時間</TableHead>
                                        <TableHead>操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                {user.name}
                                            </TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {user.is_admin ? (
                                                        <>
                                                            <ShieldCheck className="h-4 w-4 text-green-600" />
                                                            <span className="text-green-600">管理員</span>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <User className="h-4 w-4 text-gray-600" />
                                                            <span className="text-gray-600">一般使用者</span>
                                                        </>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(user.created_at).toLocaleDateString('zh-TW')}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {!user.is_admin ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => promoteUser(user.id)}
                                                            className="text-green-600 hover:text-green-700"
                                                        >
                                                            <Shield className="h-4 w-4 mr-1" />
                                                            提升為管理員
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => demoteUser(user.id)}
                                                            className="text-orange-600 hover:text-orange-700"
                                                        >
                                                            <Shield className="h-4 w-4 mr-1" />
                                                            撤銷管理員
                                                        </Button>
                                                    )}
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => deleteUser(user.id, user.name)}
                                                        className="text-red-600 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-4 w-4 mr-1" />
                                                        刪除
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
