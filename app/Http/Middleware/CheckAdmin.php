<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = 'admin'): Response
    {
        if (!Auth::check()) {
            return $this->unauthorizedResponse($request);
        }

        $user = Auth::user();
        $hasPermission = match ($permission) {
            'admin' => $this->permissionService->checkAdminPermission($user),
            'upload' => $this->permissionService->checkUploadPermission($user),
            'schedule' => $this->permissionService->checkScheduleManagementPermission($user),
            'user_management' => $this->permissionService->checkUserManagementPermission($user),
            'performance_monitoring' => $this->permissionService->checkPerformanceMonitoringPermission($user),
            default => $this->permissionService->checkAdminPermission($user),
        };

        if (!$hasPermission) {
            $this->permissionService->logPermissionCheck($user, $permission, false);
            return $this->forbiddenResponse($request);
        }

        $this->permissionService->logPermissionCheck($user, $permission, true);

        return $next($request);
    }

    private function unauthorizedResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => '請先登入',
                'error' => 'Unauthenticated',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    private function forbiddenResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => '權限不足，無法存取此功能',
                'error' => 'Insufficient permissions',
            ], 403);
        }

        return response()->view('errors.403', [
            'message' => '權限不足，無法存取此功能。如需協助，請聯絡系統管理員。',
        ], 403);
    }
}
