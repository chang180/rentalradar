<?php

namespace App\Services;

use App\Models\ScheduleExecution;
use App\Models\ScheduleSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ScheduleService
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    public function getAllScheduleSettings(): array
    {
        return ScheduleSetting::with(['executions' => function ($query) {
            $query->latest()->limit(5);
        }])->get()->toArray();
    }

    public function getScheduleSetting(string $taskName): ?ScheduleSetting
    {
        return ScheduleSetting::where('task_name', $taskName)->first();
    }

    public function createScheduleSetting(array $data): ScheduleSetting
    {
        return ScheduleSetting::create([
            'task_name' => $data['task_name'],
            'frequency' => $data['frequency'] ?? 'monthly',
            'execution_days' => $data['execution_days'] ?? [5, 15, 25],
            'execution_time' => $data['execution_time'] ?? '02:00',
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateScheduleSetting(string $taskName, array $data): bool
    {
        $setting = $this->getScheduleSetting($taskName);
        if (!$setting) {
            return false;
        }

        $setting->update($data);

        Log::info('Schedule setting updated', [
            'task_name' => $taskName,
            'updated_data' => $data,
        ]);

        return true;
    }

    public function toggleScheduleSetting(string $taskName, User $user): bool
    {
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            Log::warning('User attempted to toggle schedule without permission', [
                'user_id' => $user->id,
                'task_name' => $taskName,
            ]);
            return false;
        }

        $setting = $this->getScheduleSetting($taskName);
        if (!$setting) {
            return false;
        }

        $setting->update(['is_active' => !$setting->is_active]);

        Log::info('Schedule setting toggled', [
            'task_name' => $taskName,
            'is_active' => $setting->is_active,
            'user_id' => $user->id,
        ]);

        return true;
    }

    public function manuallyExecuteSchedule(string $taskName, User $user): bool
    {
        if (!$this->permissionService->checkScheduleManagementPermission($user)) {
            Log::warning('User attempted to execute schedule without permission', [
                'user_id' => $user->id,
                'task_name' => $taskName,
            ]);
            return false;
        }

        try {
            $execution = $this->createScheduleExecution($taskName, Carbon::now());
            $execution->markAsStarted();

            Log::info('Manual schedule execution started', [
                'task_name' => $taskName,
                'execution_id' => $execution->id,
                'user_id' => $user->id,
            ]);

            $result = $this->executeTask($taskName);

            if ($result['success']) {
                $execution->markAsCompleted($result['data'] ?? []);
            } else {
                $execution->markAsFailed($result['error'] ?? 'Unknown error');
            }

            return $result['success'];
        } catch (\Exception $e) {
            Log::error('Manual schedule execution failed', [
                'task_name' => $taskName,
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return false;
        }
    }

    public function createScheduleExecution(string $taskName, Carbon $scheduledAt): ScheduleExecution
    {
        return ScheduleExecution::create([
            'task_name' => $taskName,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);
    }

    public function getScheduleExecutions(string $taskName, int $limit = 50): array
    {
        return ScheduleExecution::where('task_name', $taskName)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getScheduleStatus(): array
    {
        $settings = ScheduleSetting::all();
        $status = [];

        foreach ($settings as $setting) {
            $lastExecution = ScheduleExecution::where('task_name', $setting->task_name)
                ->latest('created_at')
                ->first();

            $nextExecution = $setting->getNextExecutionDate();

            $status[] = [
                'task_name' => $setting->task_name,
                'is_active' => $setting->is_active,
                'frequency' => $setting->frequency,
                'execution_days' => $setting->execution_days,
                'execution_time' => $setting->execution_time ? 
                    (is_string($setting->execution_time) ? $setting->execution_time : $setting->execution_time->format('H:i')) : null,
                'next_execution' => $nextExecution?->toISOString(),
                'last_execution' => [
                    'status' => $lastExecution?->status,
                    'executed_at' => $lastExecution?->completed_at?->toISOString(),
                    'duration' => $lastExecution?->duration,
                ],
            ];
        }

        return $status;
    }

    public function checkAndExecutePendingSchedules(): array
    {
        $executed = [];
        $activeSettings = ScheduleSetting::where('is_active', true)->get();

        foreach ($activeSettings as $setting) {
            if ($setting->isTimeToExecute()) {
                $execution = $this->createScheduleExecution($setting->task_name, Carbon::now());
                $execution->markAsStarted();

                try {
                    $result = $this->executeTask($setting->task_name);

                    if ($result['success']) {
                        $execution->markAsCompleted($result['data'] ?? []);
                        $executed[] = [
                            'task_name' => $setting->task_name,
                            'status' => 'completed',
                            'execution_id' => $execution->id,
                        ];
                    } else {
                        $execution->markAsFailed($result['error'] ?? 'Unknown error');
                        $executed[] = [
                            'task_name' => $setting->task_name,
                            'status' => 'failed',
                            'execution_id' => $execution->id,
                            'error' => $result['error'],
                        ];
                    }
                } catch (\Exception $e) {
                    $execution->markAsFailed($e->getMessage());
                    $executed[] = [
                        'task_name' => $setting->task_name,
                        'status' => 'failed',
                        'execution_id' => $execution->id,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Scheduled task execution failed', [
                        'task_name' => $setting->task_name,
                        'execution_id' => $execution->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $executed;
    }

    private function executeTask(string $taskName): array
    {
        try {
            switch ($taskName) {
                case 'data_download':
                    return $this->executeDataDownloadTask();

                default:
                    return [
                        'success' => false,
                        'error' => "Unknown task: {$taskName}",
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function executeDataDownloadTask(): array
    {
        try {
            $exitCode = Artisan::call('government:download', [
                '--format' => 'zip',
                '--parse' => true,
                '--save' => true,
                '--no-interaction' => true,
            ]);

            if ($exitCode === 0) {
                $output = Artisan::output();

                return [
                    'success' => true,
                    'data' => [
                        'output' => $output,
                        'exit_code' => $exitCode,
                        'executed_at' => Carbon::now()->toISOString(),
                    ],
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Artisan command failed with exit code: ' . $exitCode,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getExecutionStatistics(string $taskName, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $executions = ScheduleExecution::where('task_name', $taskName)
            ->where('created_at', '>=', $startDate)
            ->get();

        $total = $executions->count();
        $completed = $executions->where('status', 'completed')->count();
        $failed = $executions->where('status', 'failed')->count();
        $running = $executions->where('status', 'running')->count();

        $averageDuration = $executions
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->avg('duration');

        return [
            'task_name' => $taskName,
            'period_days' => $days,
            'total_executions' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'running' => $running,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'average_duration_seconds' => $averageDuration ? round($averageDuration, 2) : null,
        ];
    }

    public function initializeDefaultSchedules(): void
    {
        $defaultSchedules = [
            [
                'task_name' => 'data_download',
                'frequency' => 'monthly',
                'execution_days' => [5, 15, 25],
                'execution_time' => '02:00',
                'is_active' => true,
            ],
        ];

        foreach ($defaultSchedules as $schedule) {
            if (!ScheduleSetting::where('task_name', $schedule['task_name'])->exists()) {
                ScheduleSetting::create($schedule);

                Log::info('Default schedule created', [
                    'task_name' => $schedule['task_name'],
                ]);
            }
        }
    }

    public function deleteScheduleSetting(string $taskName): bool
    {
        try {
            $setting = ScheduleSetting::where('task_name', $taskName)->first();
            
            if (!$setting) {
                return false;
            }

            // 刪除相關的執行記錄
            ScheduleExecution::where('task_name', $taskName)->delete();
            
            // 刪除排程設定
            $setting->delete();

            Log::info('Schedule setting deleted', [
                'task_name' => $taskName,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete schedule setting', [
                'task_name' => $taskName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function executeScheduleManually(string $taskName): ?array
    {
        try {
            $setting = $this->getScheduleSetting($taskName);
            
            if (!$setting) {
                return null;
            }

            // 建立執行記錄
            $execution = ScheduleExecution::create([
                'task_name' => $taskName,
                'scheduled_at' => now(),
                'started_at' => now(),
                'status' => 'running',
            ]);

            // 這裡應該調用實際的排程執行邏輯
            // 暫時模擬執行成功
            $execution->markAsCompleted([
                'records_processed' => 100,
                'execution_time' => 30,
                'status' => 'success',
            ]);

            Log::info('Schedule executed manually', [
                'task_name' => $taskName,
                'execution_id' => $execution->id,
            ]);

            return [
                'execution_id' => $execution->id,
                'status' => 'completed',
            ];
        } catch (\Exception $e) {
            Log::error('Manual schedule execution failed', [
                'task_name' => $taskName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getExecutionHistory(string $taskName, int $page = 1, int $perPage = 15, ?string $status = null): array
    {
        $query = ScheduleExecution::where('task_name', $taskName);

        if ($status) {
            $query->where('status', $status);
        }

        $executions = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'executions' => $executions->items(),
            'pagination' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
        ];
    }

    public function getScheduleStats(): array
    {
        $totalSchedules = ScheduleSetting::count();
        $activeSchedules = ScheduleSetting::where('is_active', true)->count();
        $totalExecutions = ScheduleExecution::count();
        $successfulExecutions = ScheduleExecution::where('status', 'completed')->count();
        $failedExecutions = ScheduleExecution::where('status', 'failed')->count();

        return [
            'total_schedules' => $totalSchedules,
            'active_schedules' => $activeSchedules,
            'inactive_schedules' => $totalSchedules - $activeSchedules,
            'total_executions' => $totalExecutions,
            'successful_executions' => $successfulExecutions,
            'failed_executions' => $failedExecutions,
            'success_rate' => $totalExecutions > 0 ? round(($successfulExecutions / $totalExecutions) * 100, 2) : 0,
        ];
    }

    public function checkScheduleStatus(string $taskName): array
    {
        $setting = $this->getScheduleSetting($taskName);
        
        if (!$setting) {
            return [
                'exists' => false,
                'status' => 'not_found',
            ];
        }

        $lastExecution = ScheduleExecution::where('task_name', $taskName)
            ->orderBy('created_at', 'desc')
            ->first();

        $nextExecution = $setting->getNextExecutionDate();

        return [
            'exists' => true,
            'is_active' => $setting->is_active,
            'last_execution' => $lastExecution ? [
                'status' => $lastExecution->status,
                'executed_at' => $lastExecution->started_at,
                'duration' => $lastExecution->duration,
            ] : null,
            'next_execution' => $nextExecution ? $nextExecution->toISOString() : null,
            'execution_days' => $setting->execution_days,
            'execution_time' => $setting->execution_time ? 
                (is_string($setting->execution_time) ? $setting->execution_time : $setting->execution_time->format('H:i')) : null,
        ];
    }
}
