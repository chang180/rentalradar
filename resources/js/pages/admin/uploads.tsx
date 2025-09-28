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
import { Head, Link } from '@inertiajs/react';
import { 
    ArrowLeft,
    Upload, 
    FileText, 
    CheckCircle, 
    XCircle, 
    Clock, 
    Play,
    Trash2,
    Download
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';

interface FileUpload {
    id: number;
    filename: string;
    original_filename: string;
    file_size: number;
    file_type: string;
    upload_status: 'pending' | 'processing' | 'completed' | 'failed';
    processing_result?: any;
    error_message?: string;
    created_at: string;
    updated_at: string;
}

interface UploadsResponse {
    uploads: FileUpload[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export default function AdminUploads() {
    const isAdmin = useAdminCheck();
    const [uploads, setUploads] = useState<FileUpload[]>([]);
    const [loading, setLoading] = useState(true);
    const [status, setStatus] = useState<string>('all');
    const [uploading, setUploading] = useState(false);
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });
    const fileInputRef = useRef<HTMLInputElement>(null);

    // 載入上傳記錄
    const loadUploads = async (page = 1, statusFilter = 'all') => {
        try {
            setLoading(true);
            const params = new URLSearchParams({
                page: page.toString(),
                per_page: '15',
                ...(statusFilter !== 'all' && { status: statusFilter }),
            });

            const response = await fetch(`/api/admin/uploads?${params}`);
            if (response.ok) {
                const data: UploadsResponse = await response.json();
                setUploads(data.uploads);
                setPagination(data.pagination);
            }
        } catch (error) {
            console.error('載入上傳記錄失敗:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (isAdmin) {
            loadUploads(1, status);
        }
    }, [isAdmin, status]);

    // 檔案上傳
    const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        try {
            setUploading(true);
            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch('/api/admin/uploads', {
                method: 'POST',
                body: formData,
            });

            if (response.ok) {
                // 重新載入上傳記錄
                loadUploads(pagination.current_page, status);
                alert('檔案上傳成功！');
            } else {
                const error = await response.json();
                alert(`上傳失敗: ${error.message}`);
            }
        } catch (error) {
            console.error('檔案上傳失敗:', error);
            alert('檔案上傳失敗');
        } finally {
            setUploading(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    };

    // 處理檔案
    const processUpload = async (uploadId: number) => {
        try {
            const response = await fetch(`/api/admin/uploads/${uploadId}/process`, {
                method: 'POST',
            });

            if (response.ok) {
                // 重新載入上傳記錄
                loadUploads(pagination.current_page, status);
                alert('檔案處理已開始！');
            } else {
                const error = await response.json();
                alert(`處理失敗: ${error.message}`);
            }
        } catch (error) {
            console.error('檔案處理失敗:', error);
            alert('檔案處理失敗');
        }
    };

    // 刪除上傳記錄
    const deleteUpload = async (uploadId: number, filename: string) => {
        if (!confirm(`確定要刪除上傳記錄 "${filename}" 嗎？`)) {
            return;
        }

        try {
            const response = await fetch(`/api/admin/uploads/${uploadId}`, {
                method: 'DELETE',
            });

            if (response.ok) {
                // 重新載入上傳記錄
                loadUploads(pagination.current_page, status);
            } else {
                const error = await response.json();
                alert(`刪除失敗: ${error.message}`);
            }
        } catch (error) {
            console.error('刪除上傳記錄失敗:', error);
            alert('刪除上傳記錄失敗');
        }
    };

    // 格式化檔案大小
    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    // 獲取狀態圖標
    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'completed':
                return <CheckCircle className="h-4 w-4 text-green-600" />;
            case 'failed':
                return <XCircle className="h-4 w-4 text-red-600" />;
            case 'processing':
                return <Clock className="h-4 w-4 text-blue-600" />;
            default:
                return <Clock className="h-4 w-4 text-gray-600" />;
        }
    };

    // 獲取狀態文字
    const getStatusText = (status: string) => {
        switch (status) {
            case 'pending':
                return '等待中';
            case 'processing':
                return '處理中';
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
                    <Upload className="mx-auto h-12 w-12 text-gray-400" />
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
            <Head title="檔案上傳管理" />

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
                    <h1 className="text-3xl font-bold text-gray-900">檔案上傳管理</h1>
                    <p className="mt-2 text-gray-600">
                        管理政府資料檔案上傳和處理
                    </p>
                </div>

                {/* 檔案上傳區域 */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>上傳政府資料檔案</CardTitle>
                        <CardDescription>
                            支援 ZIP 和 CSV 格式，檔案大小限制 100MB
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-4">
                            <div>
                                <Label htmlFor="file-upload">選擇檔案</Label>
                                <Input
                                    id="file-upload"
                                    type="file"
                                    accept=".zip,.csv"
                                    onChange={handleFileUpload}
                                    disabled={uploading}
                                    ref={fileInputRef}
                                />
                            </div>
                            <Button
                                onClick={() => fileInputRef.current?.click()}
                                disabled={uploading}
                                className="w-full md:w-auto"
                            >
                                {uploading ? (
                                    <>
                                        <Clock className="h-4 w-4 mr-2" />
                                        上傳中...
                                    </>
                                ) : (
                                    <>
                                        <Upload className="h-4 w-4 mr-2" />
                                        選擇檔案上傳
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* 篩選選項 */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>篩選上傳記錄</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="w-full md:w-48">
                            <Label htmlFor="status">狀態篩選</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger>
                                    <SelectValue placeholder="選擇狀態" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">全部</SelectItem>
                                    <SelectItem value="pending">等待中</SelectItem>
                                    <SelectItem value="processing">處理中</SelectItem>
                                    <SelectItem value="completed">已完成</SelectItem>
                                    <SelectItem value="failed">失敗</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* 上傳記錄列表 */}
                <Card>
                    <CardHeader>
                        <CardTitle>上傳記錄</CardTitle>
                        <CardDescription>
                            共 {pagination.total} 筆記錄
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
                                        <TableHead>檔案名稱</TableHead>
                                        <TableHead>檔案大小</TableHead>
                                        <TableHead>狀態</TableHead>
                                        <TableHead>上傳時間</TableHead>
                                        <TableHead>操作</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {uploads.map((upload) => (
                                        <TableRow key={upload.id}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    <FileText className="h-4 w-4 text-gray-600" />
                                                    <span>{upload.original_filename}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {formatFileSize(upload.file_size)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {getStatusIcon(upload.upload_status)}
                                                    <span>{getStatusText(upload.upload_status)}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(upload.created_at).toLocaleDateString('zh-TW')}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {upload.upload_status === 'pending' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => processUpload(upload.id)}
                                                            className="text-blue-600 hover:text-blue-700"
                                                        >
                                                            <Play className="h-4 w-4 mr-1" />
                                                            開始處理
                                                        </Button>
                                                    )}
                                                    {upload.upload_status === 'failed' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => processUpload(upload.id)}
                                                            className="text-green-600 hover:text-green-700"
                                                        >
                                                            <Play className="h-4 w-4 mr-1" />
                                                            重新處理
                                                        </Button>
                                                    )}
                                                    {upload.upload_status !== 'processing' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => deleteUpload(upload.id, upload.original_filename)}
                                                            className="text-red-600 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4 mr-1" />
                                                            刪除
                                                        </Button>
                                                    )}
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
