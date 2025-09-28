import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { adminApiRequest } from '@/utils/api';
import { 
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAdminCheck } from '@/hooks/useAdmin';
import { Head, Link } from '@inertiajs/react';
import { 
    ArrowLeft,
    Calendar, 
    Clock, 
    Play, 
    Pause, 
    Settings,
    CheckCircle,
    XCircle,
    AlertCircle
} from 'lucide-react';
import { useState, useEffect } from 'react';

interface ScheduleSetting {
    id: number;
    task_name: string;
    frequency: string;
    execution_days: number[];
    execution_time: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface ScheduleExecution {
    id: number;
    task_name: string;
    scheduled_at: string;
    started_at: string;
    completed_at: string | null;
    status: 'pending' | 'running' | 'completed' | 'failed';
    result: any;
    error_message: string | null;
}

export default function AdminSchedules() {
    const isAdmin = useAdminCheck();
    const [schedules, setSchedules] = useState<ScheduleSetting[]>([]);
    const [loading, setLoading] = useState(true);
    const [selectedSchedule, setSelectedSchedule] = useState<ScheduleSetting | null>(null);
    const [executions, setExecutions] = useState<ScheduleExecution[]>([]);
    const [showCreateForm, setShowCreateForm] = useState(false);

    // 新排程表單狀態
    const [newSchedule, setNewSchedule] = useState({
        task_name: '',
        frequency: 'monthly',
        execution_days: [5, 15, 25],
        execution_time: '02:00',
        is_active: true,
    });

    // 載入排程設定
    const loadSchedules = async () => {
        try {
            setLoading(true);
            const response = await adminApiRequest('/schedules');
            const data = response.data;
            setSchedules(data.data || []);
        } catch (error) {
            console.error('載入排程設定失敗:', error);
        } finally {
            setLoading(false);
        }
    };

    // 載入排程執行歷史
    const loadExecutions = async (taskName: string) => {
        try {
            const response = await adminApiRequest(`/schedules/${taskName}/history`);
            const data = response.data;
            setExecutions(data.data?.executions || []);
        } catch (error) {
            console.error('載入執行歷史失敗:', error);
        }
    };

    useEffect(() => {
        if (isAdmin) {
            loadSchedules();
        }
    }, [isAdmin]);

    // 創建新排程
    const createSchedule = async () => {
        try {
            await adminApiRequest('/schedules', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(newSchedule),
            });

            loadSchedules();
            setShowCreateForm(false);
            setNewSchedule({
                task_name: '',
                frequency: 'monthly',
                execution_days: [5, 15, 25],
                execution_time: '02:00',
                is_active: true,
            });
            alert('排程創建成功！');
        } catch (error) {
            console.error('創建排程失敗:', error);
            alert('創建排程失敗');
        }
    };

    // 更新排程狀態
    const updateScheduleStatus = async (taskName: string, isActive: boolean) => {
        try {
            await adminApiRequest(`/schedules/${taskName}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ is_active: isActive }),
            });

            loadSchedules();
        } catch (error) {
            console.error('更新排程狀態失敗:', error);
            alert('更新排程狀態失敗');
        }
    };

    // 手動執行排程
    const executeSchedule = async (taskName: string) => {
        try {
            await adminApiRequest(`/schedules/${taskName}/execute`, {
                method: 'POST',
            });

            alert('排程執行已開始！');
            if (selectedSchedule?.task_name === taskName) {
                loadExecutions(taskName);
            }
        } catch (error) {
            console.error('手動執行排程失敗:', error);
            alert('手動執行排程失敗');
        }
    };

    // 刪除排程
    const deleteSchedule = async (taskName: string) => {
        if (!confirm(`確定要刪除排程 "${taskName}" 嗎？`)) {
            return;
        }

        try {
            await adminApiRequest(`/schedules/${taskName}`, {
                method: 'DELETE',
            });

            loadSchedules();
            if (selectedSchedule?.task_name === taskName) {
                setSelectedSchedule(null);
                setExecutions([]);
            }
        } catch (error) {
            console.error('刪除排程失敗:', error);
            alert('刪除排程失敗');
        }
    };

    // 獲取狀態圖標
    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'completed':
                return <CheckCircle className="h-4 w-4 text-green-600" />;
            case 'failed':
                return <XCircle className="h-4 w-4 text-red-600" />;
            case 'running':
                return <Clock className="h-4 w-4 text-blue-600" />;
            case 'pending':
                return <AlertCircle className="h-4 w-4 text-yellow-600" />;
            default:
                return <AlertCircle className="h-4 w-4 text-gray-600" />;
        }
    };

    // 獲取狀態文字
    const getStatusText = (status: string) => {
        switch (status) {
            case 'pending':
                return '等待中';
            case 'running':
                return '執行中';
            case 'completed':
                return '已完成';
            case 'failed':
                return '失敗';
            default:
                return status;
        }
    };

    if (!isAdmin) {
        return (
            <div className="container mx-auto py-8">
                <div className="text-center">
                    <Calendar className="mx-auto h-12 w-12 text-gray-400" />
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
            <Head title="排程管理" />

            <div className="container mx-auto py-8">
                <div className="mb-8">
                    <div className="flex items-center gap-4 mb-4">
                        <Link 
                            href="/dashboard" 
                            className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            返回儀表板
                        </Link>
                    </div>
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">排程管理</h1>
                            <p className="mt-2 text-gray-600">
                                管理系統自動化任務排程
                            </p>
                        </div>
                        <Button onClick={() => setShowCreateForm(true)}>
                            <Settings className="h-4 w-4 mr-2" />
                            新增排程
                        </Button>
                    </div>
                </div>

                {/* 創建排程表單 */}
                {showCreateForm && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>新增排程</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="task_name">任務名稱</Label>
                                    <Input
                                        id="task_name"
                                        value={newSchedule.task_name}
                                        onChange={(e) => setNewSchedule({
                                            ...newSchedule,
                                            task_name: e.target.value
                                        })}
                                        placeholder="輸入任務名稱"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="frequency">執行頻率</Label>
                                    <Select
                                        value={newSchedule.frequency}
                                        onValueChange={(value) => setNewSchedule({
                                            ...newSchedule,
                                            frequency: value
                                        })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="daily">每日</SelectItem>
                                            <SelectItem value="weekly">每週</SelectItem>
                                            <SelectItem value="monthly">每月</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="execution_time">執行時間</Label>
                                    <Input
                                        id="execution_time"
                                        type="time"
                                        value={newSchedule.execution_time}
                                        onChange={(e) => setNewSchedule({
                                            ...newSchedule,
                                            execution_time: e.target.value
                                        })}
                                    />
                                </div>
                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="is_active"
                                        checked={newSchedule.is_active}
                                        onCheckedChange={(checked) => setNewSchedule({
                                            ...newSchedule,
                                            is_active: checked
                                        })}
                                    />
                                    <Label htmlFor="is_active">啟用排程</Label>
                                </div>
                            </div>
                            <div className="mt-4 flex gap-2">
                                <Button onClick={createSchedule}>
                                    創建排程
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowCreateForm(false)}
                                >
                                    取消
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* 排程列表 */}
                    <Card>
                        <CardHeader>
                            <CardTitle>排程列表</CardTitle>
                            <CardDescription>
                                共 {schedules.length} 個排程
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {loading ? (
                                <div className="text-center py-8">
                                    <div className="text-gray-500">載入中...</div>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {schedules.map((schedule) => (
                                        <div
                                            key={schedule.id}
                                            className={`rounded-lg border p-4 cursor-pointer transition-colors ${
                                                selectedSchedule?.id === schedule.id
                                                    ? 'border-blue-500 bg-blue-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                            onClick={() => {
                                                setSelectedSchedule(schedule);
                                                loadExecutions(schedule.task_name);
                                            }}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h3 className="font-semibold">{schedule.task_name}</h3>
                                                    <p className="text-sm text-gray-600">
                                                        {schedule.frequency} • {schedule.execution_time}
                                                    </p>
                                                    <p className="text-sm text-gray-600">
                                                        執行日: {schedule.execution_days.join(', ')}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Switch
                                                        checked={schedule.is_active}
                                                        onCheckedChange={(checked) => 
                                                            updateScheduleStatus(schedule.task_name, checked)
                                                        }
                                                        onClick={(e) => e.stopPropagation()}
                                                    />
                                                </div>
                                            </div>
                                            <div className="mt-3 flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        executeSchedule(schedule.task_name);
                                                    }}
                                                    className="text-blue-600 hover:text-blue-700"
                                                >
                                                    <Play className="h-4 w-4 mr-1" />
                                                    手動執行
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        deleteSchedule(schedule.task_name);
                                                    }}
                                                    className="text-red-600 hover:text-red-700"
                                                >
                                                    <XCircle className="h-4 w-4 mr-1" />
                                                    刪除
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* 執行歷史 */}
                    <Card>
                        <CardHeader>
                            <CardTitle>執行歷史</CardTitle>
                            <CardDescription>
                                {selectedSchedule ? `${selectedSchedule.task_name} 的執行記錄` : '選擇排程查看執行歷史'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {selectedSchedule ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>執行時間</TableHead>
                                            <TableHead>狀態</TableHead>
                                            <TableHead>結果</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {executions.map((execution) => (
                                            <TableRow key={execution.id}>
                                                <TableCell>
                                                    {new Date(execution.started_at).toLocaleString('zh-TW')}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {getStatusIcon(execution.status)}
                                                        <span>{getStatusText(execution.status)}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {execution.status === 'completed' && execution.result ? (
                                                        <div className="text-sm">
                                                            <div>處理記錄: {execution.result.records_processed || 0}</div>
                                                            <div>執行時間: {execution.result.execution_time || 0}s</div>
                                                        </div>
                                                    ) : execution.status === 'failed' ? (
                                                        <div className="text-sm text-red-600">
                                                            {execution.error_message || '執行失敗'}
                                                        </div>
                                                    ) : (
                                                        <span className="text-gray-500">-</span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <div className="text-center py-8">
                                    <Calendar className="mx-auto h-12 w-12 text-gray-400" />
                                    <p className="mt-2 text-gray-500">請選擇一個排程查看執行歷史</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
