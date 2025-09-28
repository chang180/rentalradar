<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    public function checkAdminPermission(User $user): bool
    {
        return $this->getCachedUserPermission($user->id, function () use ($user) {
            return $user->isAdmin();
        });
    }

    public function checkUploadPermission(User $user): bool
    {
        return $this->checkAdminPermission($user);
    }

    public function checkScheduleManagementPermission(User $user): bool
    {
        return $this->checkAdminPermission($user);
    }

    public function checkUserManagementPermission(User $user): bool
    {
        return $this->checkAdminPermission($user);
    }

    public function checkPerformanceMonitoringPermission(User $user): bool
    {
        return $this->checkAdminPermission($user);
    }

    public function promoteUserToAdmin(User $user): bool
    {
        try {
            $user->update(['is_admin' => true]);
            $this->clearUserPermissionCache($user->id);

            Log::info('User promoted to admin', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to promote user to admin', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function demoteUserFromAdmin(User $user): bool
    {
        try {
            $user->update(['is_admin' => false]);
            $this->clearUserPermissionCache($user->id);

            Log::info('User demoted from admin', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to demote user from admin', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getUserPermissions(User $user): array
    {
        return [
            'is_admin' => $this->checkAdminPermission($user),
            'can_upload' => $this->checkUploadPermission($user),
            'can_manage_schedules' => $this->checkScheduleManagementPermission($user),
            'can_manage_users' => $this->checkUserManagementPermission($user),
            'can_view_performance' => $this->checkPerformanceMonitoringPermission($user),
        ];
    }

    public function logPermissionCheck(User $user, string $permission, bool $granted): void
    {
        Log::info('Permission check', [
            'user_id' => $user->id,
            'email' => $user->email,
            'permission' => $permission,
            'granted' => $granted,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function getCachedUserPermission(int $userId, callable $permissionCheck): bool
    {
        $cacheKey = "user_permissions:{$userId}";

        return Cache::remember($cacheKey, now()->addMinutes(10), $permissionCheck);
    }

    private function clearUserPermissionCache(int $userId): void
    {
        Cache::forget("user_permissions:{$userId}");
    }

    public function clearAllPermissionCache(): void
    {
        Cache::tags(['user_permissions'])->flush();
    }
}
