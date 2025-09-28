<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleService $scheduleService,
        private PermissionService $permissionService
    ) {}

    /**
     * 獲取所有排程設定
     */
    public function getSchedules(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $schedules = $this->scheduleService->getAllScheduleSettings();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入排程設定：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取特定排程設定
     */
    public function getSchedule(string $taskName): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $schedule = $this->scheduleService->getScheduleSetting($taskName);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => '找不到指定的排程設定',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入排程設定：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 建立新的排程設定
     */
    public function createSchedule(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'task_name' => 'required|string|max:255|unique:schedule_settings,task_name',
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'execution_days' => 'required|array|min:1',
            'execution_days.*' => 'integer|min:1|max:31',
            'execution_time' => 'required|date_format:H:i',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $schedule = $this->scheduleService->createScheduleSetting($request->all());

            return response()->json([
                'success' => true,
                'message' => '排程設定已建立',
                'data' => $schedule,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '建立排程設定失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新排程設定
     */
    public function updateSchedule(Request $request, string $taskName): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'frequency' => 'sometimes|string|in:daily,weekly,monthly',
            'execution_days' => 'sometimes|array|min:1',
            'execution_days.*' => 'integer|min:1|max:31',
            'execution_time' => 'sometimes|date_format:H:i',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->scheduleService->updateScheduleSetting($taskName, $request->all());

            if ($result) {
                $schedule = $this->scheduleService->getScheduleSetting($taskName);
                
                return response()->json([
                    'success' => true,
                    'message' => '排程設定已更新',
                    'data' => $schedule,
                    'timestamp' => now()->toISOString(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '找不到指定的排程設定',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新排程設定失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除排程設定
     */
    public function deleteSchedule(string $taskName): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $result = $this->scheduleService->deleteScheduleSetting($taskName);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '排程設定已刪除',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '找不到指定的排程設定',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '刪除排程設定失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 手動觸發排程執行
     */
    public function executeSchedule(Request $request, string $taskName): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $result = $this->scheduleService->executeScheduleManually($taskName);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '排程執行已開始',
                    'data' => [
                        'task_name' => $taskName,
                        'execution_id' => $result['execution_id'],
                        'status' => $result['status'],
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '找不到指定的排程設定或執行失敗',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '排程執行失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取排程執行歷史
     */
    public function getExecutionHistory(Request $request, string $taskName): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status'); // pending, running, completed, failed

            $history = $this->scheduleService->getExecutionHistory($taskName, $page, $perPage, $status);

            return response()->json([
                'success' => true,
                'data' => $history,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入執行歷史：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取排程統計資料
     */
    public function getScheduleStats(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $stats = $this->scheduleService->getScheduleStats();

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
     * 檢查排程執行狀態
     */
    public function checkScheduleStatus(string $taskName): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $status = $this->scheduleService->checkScheduleStatus($taskName);

            return response()->json([
                'success' => true,
                'data' => $status,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法檢查排程狀態：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 初始化預設排程設定
     */
    public function initializeDefaultSchedules(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $result = $this->scheduleService->initializeDefaultSchedules();

            return response()->json([
                'success' => true,
                'message' => '預設排程設定已初始化',
                'data' => $result,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '初始化預設排程失敗：' . $e->getMessage(),
            ], 500);
        }
    }
}