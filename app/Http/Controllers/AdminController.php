<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PermissionService;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function __construct(
        private PermissionService $permissionService,
        private UserManagementService $userManagementService
    ) {}

    /**
     * 獲取管理員儀表板資料
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkAdminPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $stats = $this->userManagementService->getAdminDashboardStats();
            $recentUsers = $this->userManagementService->getRecentUsers(10);
            $systemStatus = $this->userManagementService->getSystemStatus();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_users' => $recentUsers,
                    'system_status' => $systemStatus,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入管理員儀表板：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取使用者列表
     */
    public function getUsers(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkUserManagementPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $role = $request->get('role'); // 'admin' or 'user'

            $users = $this->userManagementService->getUsers($page, $perPage, $search, $role);

            return response()->json([
                'success' => true,
                'data' => $users,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法載入使用者列表：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 提升使用者為管理員
     */
    public function promoteUser(Request $request, User $user): JsonResponse
    {
        $requestingUser = Auth::user();
        
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $targetUser = $user;
            
            if ($targetUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => '該使用者已經是管理員',
                ], 400);
            }

            $result = $this->permissionService->promoteUserToAdmin($targetUser);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '使用者已成功提升為管理員',
                    'data' => [
                        'user_id' => $targetUser->id,
                        'name' => $targetUser->name,
                        'email' => $targetUser->email,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '提升使用者權限失敗',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '操作失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 撤銷使用者管理員權限
     */
    public function demoteUser(Request $request, User $user): JsonResponse
    {
        $requestingUser = Auth::user();
        
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $targetUser = $user;
            
            // 防止撤銷自己的管理員權限
            if ($targetUser->id === $requestingUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => '無法撤銷自己的管理員權限',
                ], 400);
            }

            if (!$targetUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => '該使用者不是管理員',
                ], 400);
            }

            $result = $this->permissionService->demoteUserFromAdmin($targetUser);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '使用者管理員權限已撤銷',
                    'data' => [
                        'user_id' => $targetUser->id,
                        'name' => $targetUser->name,
                        'email' => $targetUser->email,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '撤銷使用者權限失敗',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '操作失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除使用者
     */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        $requestingUser = Auth::user();
        
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $targetUser = $user;
            
            // 防止刪除自己
            if ($targetUser->id === $requestingUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => '無法刪除自己的帳號',
                ], 400);
            }

            $result = $this->userManagementService->deleteUser($targetUser->id, $requestingUser);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => '使用者已成功刪除',
                    'data' => [
                        'user_id' => $targetUser->id,
                        'name' => $targetUser->name,
                        'email' => $targetUser->email,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '操作失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取使用者權限資訊
     */
    public function getUserPermissions(): JsonResponse
    {
        $user = Auth::user();
        
        try {
            $permissions = $this->permissionService->getUserPermissions($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'permissions' => $permissions,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '無法獲取權限資訊：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 清除權限快取
     */
    public function clearPermissionCache(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$this->permissionService->checkAdminPermission($user)) {
            return response()->json([
                'message' => '權限不足',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        try {
            $this->permissionService->clearAllPermissionCache();

            return response()->json([
                'success' => true,
                'message' => '權限快取已清除',
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '清除快取失敗：' . $e->getMessage(),
            ], 500);
        }
    }
}