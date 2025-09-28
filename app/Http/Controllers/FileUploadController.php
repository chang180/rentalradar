<?php

namespace App\Http\Controllers;

use App\Models\FileUpload;
use App\Services\FileUploadService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private PermissionService $permissionService
    ) {}

    /**
     * 上傳政府資料檔案
     */
    public function upload(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUploadPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:102400', // 100MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '檔案驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $uploadResult = $this->fileUploadService->uploadFile($file, $user);

            if ($uploadResult) {
                return response()->json([
                    'success' => true,
                    'message' => '檔案上傳成功',
                    'data' => [
                        'upload_id' => $uploadResult->id,
                        'filename' => $uploadResult->original_filename,
                        'file_size' => $uploadResult->file_size,
                        'upload_status' => $uploadResult->upload_status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '檔案上傳失敗',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '檔案上傳失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取檔案上傳歷史
     */
    public function getUploadHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUploadPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status'); // pending, processing, completed, failed

            $uploads = $this->fileUploadService->getUploadHistory($user, $page, $perPage, $status);

            return response()->json([
                'success' => true,
                'data' => $uploads,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入上傳歷史：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取檔案上傳詳情
     */
    public function getUploadDetails(int $uploadId): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUploadPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $upload = FileUpload::where('id', $uploadId)
                ->where('user_id', $user->id)
                ->first();

            if (!$upload) {
                return response()->json([
                    'success' => false,
                    'message' => '找不到指定的上傳記錄',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $upload->id,
                    'filename' => $upload->filename,
                    'original_filename' => $upload->original_filename,
                    'file_size' => $upload->file_size,
                    'file_type' => $upload->file_type,
                    'upload_status' => $upload->upload_status,
                    'processing_result' => $upload->processing_result,
                    'error_message' => $upload->error_message,
                    'created_at' => $upload->created_at,
                    'updated_at' => $upload->updated_at,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入上傳詳情：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 手動觸發檔案處理
     */
    public function processUpload(int $uploadId): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUploadPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $upload = FileUpload::where('id', $uploadId)
                ->where('user_id', $user->id)
                ->first();

            if (!$upload) {
                return response()->json([
                    'success' => false,
                    'message' => '找不到指定的上傳記錄',
                ], 404);
            }

            if ($upload->upload_status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => '檔案已經處理完成',
                ], 400);
            }

            $result = $this->fileUploadService->processUpload($upload);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '檔案處理已開始',
                    'data' => [
                        'upload_id' => $upload->id,
                        'status' => $upload->fresh()->upload_status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '檔案處理失敗',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '檔案處理失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除上傳記錄
     */
    public function deleteUpload(int $uploadId): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUploadPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $upload = FileUpload::where('id', $uploadId)
                ->where('user_id', $user->id)
                ->first();

            if (!$upload) {
                return response()->json([
                    'success' => false,
                    'message' => '找不到指定的上傳記錄',
                ], 404);
            }

            // 如果檔案正在處理中，不允許刪除
            if ($upload->upload_status === 'processing') {
                return response()->json([
                    'success' => false,
                    'message' => '檔案正在處理中，無法刪除',
                ], 400);
            }

            $result = $this->fileUploadService->deleteUpload($upload);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '上傳記錄已刪除',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '刪除上傳記錄失敗',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '刪除失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取上傳統計資料
     */
    public function getUploadStats(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUploadPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $stats = $this->fileUploadService->getUploadStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入統計資料：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 驗證檔案格式
     */
    public function validateFile(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUploadPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '檔案驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $validationResult = $this->fileUploadService->validateFile($file);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => empty($validationResult),
                    'errors' => $validationResult,
                    'file_info' => [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '檔案驗證失敗：' . $e->getMessage(),
            ], 500);
        }
    }
}