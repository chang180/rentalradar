<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserManagementService
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    public function getAllUsers(User $requestingUser, int $perPage = 20): LengthAwarePaginator
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        return User::select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUserById(int $userId, User $requestingUser): ?User
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return null;
        }

        return User::find($userId);
    }

    public function promoteUserToAdmin(int $userId, User $requestingUser): array
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return [
                'success' => false,
                'message' => '權限不足，無法執行此操作',
            ];
        }

        $user = User::find($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => '找不到指定的使用者',
            ];
        }

        if ($user->isAdmin()) {
            return [
                'success' => false,
                'message' => '該使用者已經是管理員',
            ];
        }

        $success = $this->permissionService->promoteUserToAdmin($user);

        if ($success) {
            Log::info('User promoted to admin', [
                'promoted_user_id' => $user->id,
                'promoted_user_email' => $user->email,
                'admin_user_id' => $requestingUser->id,
                'admin_user_email' => $requestingUser->email,
            ]);

            return [
                'success' => true,
                'message' => '成功提升使用者為管理員',
                'user' => $user->fresh(),
            ];
        }

        return [
            'success' => false,
            'message' => '提升管理員權限失敗',
        ];
    }

    public function demoteUserFromAdmin(int $userId, User $requestingUser): array
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return [
                'success' => false,
                'message' => '權限不足，無法執行此操作',
            ];
        }

        $user = User::find($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => '找不到指定的使用者',
            ];
        }

        if ($user->id === $requestingUser->id) {
            return [
                'success' => false,
                'message' => '不能移除自己的管理員權限',
            ];
        }

        if (!$user->isAdmin()) {
            return [
                'success' => false,
                'message' => '該使用者不是管理員',
            ];
        }

        $success = $this->permissionService->demoteUserFromAdmin($user);

        if ($success) {
            Log::info('User demoted from admin', [
                'demoted_user_id' => $user->id,
                'demoted_user_email' => $user->email,
                'admin_user_id' => $requestingUser->id,
                'admin_user_email' => $requestingUser->email,
            ]);

            return [
                'success' => true,
                'message' => '成功移除使用者管理員權限',
                'user' => $user->fresh(),
            ];
        }

        return [
            'success' => false,
            'message' => '移除管理員權限失敗',
        ];
    }

    public function deleteUser(int $userId, User $requestingUser): array
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return [
                'success' => false,
                'message' => '權限不足，無法執行此操作',
            ];
        }

        $user = User::find($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => '找不到指定的使用者',
            ];
        }

        if ($user->id === $requestingUser->id) {
            return [
                'success' => false,
                'message' => '不能刪除自己的帳號',
            ];
        }

        try {
            $userEmail = $user->email;
            $userName = $user->name;

            $user->delete();

            Log::warning('User deleted', [
                'deleted_user_id' => $userId,
                'deleted_user_email' => $userEmail,
                'deleted_user_name' => $userName,
                'admin_user_id' => $requestingUser->id,
                'admin_user_email' => $requestingUser->email,
            ]);

            return [
                'success' => true,
                'message' => '成功刪除使用者帳號',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $userId,
                'admin_user_id' => $requestingUser->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '刪除使用者帳號失敗',
            ];
        }
    }

    public function updateUser(int $userId, array $data, User $requestingUser): array
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return [
                'success' => false,
                'message' => '權限不足，無法執行此操作',
            ];
        }

        $user = User::find($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => '找不到指定的使用者',
            ];
        }

        $validator = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        try {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }

            if (isset($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

            Log::info('User updated', [
                'updated_user_id' => $user->id,
                'updated_fields' => array_keys($updateData),
                'admin_user_id' => $requestingUser->id,
            ]);

            return [
                'success' => true,
                'message' => '成功更新使用者資料',
                'user' => $user->fresh(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update user', [
                'user_id' => $userId,
                'admin_user_id' => $requestingUser->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '更新使用者資料失敗',
            ];
        }
    }

    public function createUser(array $data, User $requestingUser): array
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return [
                'success' => false,
                'message' => '權限不足，無法執行此操作',
            ];
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'is_admin' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_admin' => $data['is_admin'] ?? false,
                'email_verified_at' => now(),
            ]);

            Log::info('User created', [
                'created_user_id' => $user->id,
                'created_user_email' => $user->email,
                'is_admin' => $user->is_admin,
                'admin_user_id' => $requestingUser->id,
            ]);

            return [
                'success' => true,
                'message' => '成功建立新使用者',
                'user' => $user,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'admin_user_id' => $requestingUser->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '建立使用者失敗',
            ];
        }
    }

    public function getUserStatistics(User $requestingUser): array
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return [];
        }

        $totalUsers = User::count();
        $adminUsers = User::where('is_admin', true)->count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $newUsersThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

        return [
            'total_users' => $totalUsers,
            'admin_users' => $adminUsers,
            'regular_users' => $totalUsers - $adminUsers,
            'verified_users' => $verifiedUsers,
            'unverified_users' => $totalUsers - $verifiedUsers,
            'new_users_this_month' => $newUsersThisMonth,
            'admin_percentage' => $totalUsers > 0 ? round(($adminUsers / $totalUsers) * 100, 2) : 0,
            'verification_rate' => $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 2) : 0,
        ];
    }

    public function getRecentUserActivity(User $requestingUser, int $limit = 10): array
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return [];
        }

        return User::select(['id', 'name', 'email', 'is_admin', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function searchUsers(string $query, User $requestingUser, int $perPage = 20): LengthAwarePaginator
    {
        if (!$this->permissionService->checkUserManagementPermission($requestingUser)) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        return User::select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getAdminDashboardStats(): array
    {
        $totalUsers = User::count();
        $adminUsers = User::where('is_admin', true)->count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $newUsersThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

        return [
            'total_users' => $totalUsers,
            'admin_users' => $adminUsers,
            'regular_users' => $totalUsers - $adminUsers,
            'verified_users' => $verifiedUsers,
            'new_users_this_month' => $newUsersThisMonth,
        ];
    }

    public function getRecentUsers(int $limit = 10): array
    {
        return User::select(['id', 'name', 'email', 'is_admin', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getSystemStatus(): array
    {
        return [
            'database_status' => 'connected',
            'cache_status' => 'active',
            'queue_status' => 'running',
            'last_maintenance' => now()->subDays(1)->toISOString(),
        ];
    }

    public function getUsers(int $page = 1, int $perPage = 15, ?string $search = null, ?string $role = null): array
    {
        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role === 'admin') {
            $query->where('is_admin', true);
        } elseif ($role === 'user') {
            $query->where('is_admin', false);
        }

        $users = $query->select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ];
    }

}
