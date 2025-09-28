<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Prediction;
use App\Models\Recommendation;
use App\Models\RiskAssessment;
use App\Models\Anomaly;
use App\Models\FileUpload;
use App\Models\ScheduleExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DataRetentionService
{
    /**
     * 資料保留政策配置
     */
    private const RETENTION_POLICIES = [
        // 核心資料 - 2年保留
        'properties' => [
            'retention_days' => 730, // 2年
            'archive_before_delete' => true,
            'priority' => 'high',
        ],
        
        // AI 相關資料 - 1年保留
        'predictions' => [
            'retention_days' => 365, // 1年
            'archive_before_delete' => true,
            'priority' => 'medium',
        ],
        
        'recommendations' => [
            'retention_days' => 365, // 1年
            'archive_before_delete' => true,
            'priority' => 'medium',
        ],
        
        'risk_assessments' => [
            'retention_days' => 365, // 1年
            'archive_before_delete' => true,
            'priority' => 'medium',
        ],
        
        // 異常資料 - 6個月保留
        'anomalies' => [
            'retention_days' => 180, // 6個月
            'archive_before_delete' => false,
            'priority' => 'low',
        ],
        
        // 檔案上傳記錄 - 3個月保留
        'file_uploads' => [
            'retention_days' => 90, // 3個月
            'archive_before_delete' => false,
            'priority' => 'low',
        ],
        
        // 排程執行記錄 - 1個月保留
        'schedule_executions' => [
            'retention_days' => 30, // 1個月
            'archive_before_delete' => false,
            'priority' => 'low',
        ],
        
        // 快取資料 - 7天保留
        'cache' => [
            'retention_days' => 7,
            'archive_before_delete' => false,
            'priority' => 'low',
        ],
        
        // 會話資料 - 1天保留
        'sessions' => [
            'retention_days' => 1,
            'archive_before_delete' => false,
            'priority' => 'low',
        ],
    ];

    /**
     * 執行資料清理
     */
    public function cleanupExpiredData(): array
    {
        $results = [];
        $totalDeleted = 0;
        $totalArchived = 0;
        $totalSpaceFreed = 0;

        Log::info('開始執行資料清理', [
            'timestamp' => now()->toISOString(),
        ]);

        foreach (self::RETENTION_POLICIES as $table => $policy) {
            try {
                $result = $this->cleanupTable($table, $policy);
                $results[$table] = $result;
                $totalDeleted += $result['deleted_count'];
                $totalArchived += $result['archived_count'];
                $totalSpaceFreed += $result['space_freed'];
                
                Log::info("資料表 {$table} 清理完成", $result);
            } catch (\Exception $e) {
                Log::error("資料表 {$table} 清理失敗", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $results[$table] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // 清理檔案系統
        $fileCleanupResult = $this->cleanupFiles();
        $results['files'] = $fileCleanupResult;
        $totalSpaceFreed += $fileCleanupResult['space_freed'];

        Log::info('資料清理完成', [
            'total_deleted' => $totalDeleted,
            'total_archived' => $totalArchived,
            'total_space_freed' => $totalSpaceFreed,
        ]);

        return [
            'success' => true,
            'summary' => [
                'total_deleted' => $totalDeleted,
                'total_archived' => $totalArchived,
                'total_space_freed' => $totalSpaceFreed,
            ],
            'details' => $results,
        ];
    }

    /**
     * 清理特定資料表
     */
    private function cleanupTable(string $table, array $policy): array
    {
        $cutoffDate = now()->subDays($policy['retention_days']);
        $deletedCount = 0;
        $archivedCount = 0;
        $spaceFreed = 0;

        // 如果需要歸檔，先歸檔再刪除
        if ($policy['archive_before_delete']) {
            $archivedCount = $this->archiveTableData($table, $cutoffDate);
        }

        // 刪除過期資料
        $deletedCount = $this->deleteExpiredData($table, $cutoffDate);
        
        // 估算釋放空間（簡化計算）
        $spaceFreed = $deletedCount * $this->estimateRecordSize($table);

        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'archived_count' => $archivedCount,
            'space_freed' => $spaceFreed,
            'cutoff_date' => $cutoffDate->toISOString(),
        ];
    }

    /**
     * 歸檔資料表資料
     */
    private function archiveTableData(string $table, Carbon $cutoffDate): int
    {
        $archivePath = storage_path("archives/{$table}");
        
        // 建立歸檔目錄
        if (!is_dir($archivePath)) {
            mkdir($archivePath, 0755, true);
        }

        $archivedCount = 0;
        $batchSize = 1000;

        // 分批處理歸檔
        do {
            $records = DB::table($table)
                ->where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            // 儲存到 JSON 檔案
            $filename = "{$table}_" . now()->format('Y-m-d_H-i-s') . '.json';
            $filepath = $archivePath . '/' . $filename;
            
            file_put_contents($filepath, json_encode($records->toArray(), JSON_PRETTY_PRINT));
            
            $archivedCount += $records->count();
            
            Log::info("歸檔資料表 {$table} 批次", [
                'count' => $records->count(),
                'file' => $filename,
            ]);
            
        } while ($records->count() === $batchSize);

        return $archivedCount;
    }

    /**
     * 刪除過期資料
     */
    private function deleteExpiredData(string $table, Carbon $cutoffDate): int
    {
        // 根據資料表類型選擇不同的刪除策略
        switch ($table) {
            case 'properties':
                return $this->deleteExpiredProperties($cutoffDate);
            case 'predictions':
                return $this->deleteExpiredPredictions($cutoffDate);
            case 'recommendations':
                return $this->deleteExpiredRecommendations($cutoffDate);
            case 'risk_assessments':
                return $this->deleteExpiredRiskAssessments($cutoffDate);
            case 'anomalies':
                return $this->deleteExpiredAnomalies($cutoffDate);
            case 'file_uploads':
                return $this->deleteExpiredFileUploads($cutoffDate);
            case 'schedule_executions':
                return $this->deleteExpiredScheduleExecutions($cutoffDate);
            case 'cache':
                return $this->deleteExpiredCache($cutoffDate);
            case 'sessions':
                return $this->deleteExpiredSessions($cutoffDate);
            default:
                return 0;
        }
    }

    /**
     * 刪除過期的租屋資料
     */
    private function deleteExpiredProperties(Carbon $cutoffDate): int
    {
        // 先刪除相關的 AI 資料
        $propertyIds = Property::where('created_at', '<', $cutoffDate)
            ->pluck('id')
            ->toArray();

        if (!empty($propertyIds)) {
            // 刪除相關的預測資料
            Prediction::whereIn('property_id', $propertyIds)->delete();
            
            // 刪除相關的推薦資料
            Recommendation::whereIn('property_id', $propertyIds)->delete();
            
            // 刪除相關的風險評估
            RiskAssessment::whereIn('property_id', $propertyIds)->delete();
            
            // 刪除相關的異常資料
            Anomaly::whereIn('property_id', $propertyIds)->delete();
        }

        // 刪除租屋資料
        return Property::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 刪除過期的預測資料
     */
    private function deleteExpiredPredictions(Carbon $cutoffDate): int
    {
        return Prediction::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 刪除過期的推薦資料
     */
    private function deleteExpiredRecommendations(Carbon $cutoffDate): int
    {
        return Recommendation::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 刪除過期的風險評估
     */
    private function deleteExpiredRiskAssessments(Carbon $cutoffDate): int
    {
        return RiskAssessment::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 刪除過期的異常資料
     */
    private function deleteExpiredAnomalies(Carbon $cutoffDate): int
    {
        return Anomaly::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 刪除過期的檔案上傳記錄
     */
    private function deleteExpiredFileUploads(Carbon $cutoffDate): int
    {
        $fileUploads = FileUpload::where('created_at', '<', $cutoffDate)->get();
        
        // 刪除實際檔案
        foreach ($fileUploads as $fileUpload) {
            if (Storage::exists($fileUpload->upload_path)) {
                Storage::delete($fileUpload->upload_path);
            }
        }
        
        return FileUpload::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 刪除過期的排程執行記錄
     */
    private function deleteExpiredScheduleExecutions(Carbon $cutoffDate): int
    {
        return ScheduleExecution::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * 刪除過期的快取
     */
    private function deleteExpiredCache(Carbon $cutoffDate): int
    {
        return DB::table('cache')
            ->where('expiration', '<', $cutoffDate->timestamp)
            ->delete();
    }

    /**
     * 刪除過期的會話
     */
    private function deleteExpiredSessions(Carbon $cutoffDate): int
    {
        return DB::table('sessions')
            ->where('last_activity', '<', $cutoffDate->timestamp)
            ->delete();
    }

    /**
     * 清理檔案系統
     */
    private function cleanupFiles(): array
    {
        $spaceFreed = 0;
        $deletedFiles = 0;

        // 清理政府資料檔案（保留7天）
        $governmentFiles = Storage::files('government-data');
        $cutoffTime = now()->subDays(7)->timestamp;
        
        foreach ($governmentFiles as $file) {
            if (Storage::lastModified($file) < $cutoffTime) {
                $fileSize = Storage::size($file);
                Storage::delete($file);
                $spaceFreed += $fileSize;
                $deletedFiles++;
            }
        }

        // 清理上傳檔案（保留30天）
        $uploadFiles = Storage::files('uploads');
        $uploadCutoffTime = now()->subDays(30)->timestamp;
        
        foreach ($uploadFiles as $file) {
            if (Storage::lastModified($file) < $uploadCutoffTime) {
                $fileSize = Storage::size($file);
                Storage::delete($file);
                $spaceFreed += $fileSize;
                $deletedFiles++;
            }
        }

        // 清理日誌檔案（保留7天）
        $logFiles = glob(storage_path('logs/*.log'));
        $logCutoffTime = now()->subDays(7)->timestamp;
        
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < $logCutoffTime) {
                $fileSize = filesize($logFile);
                unlink($logFile);
                $spaceFreed += $fileSize;
                $deletedFiles++;
            }
        }

        return [
            'success' => true,
            'deleted_files' => $deletedFiles,
            'space_freed' => $spaceFreed,
        ];
    }

    /**
     * 估算記錄大小
     */
    private function estimateRecordSize(string $table): int
    {
        // 根據資料表類型估算平均記錄大小（位元組）
        $sizes = [
            'properties' => 500,
            'predictions' => 200,
            'recommendations' => 300,
            'risk_assessments' => 250,
            'anomalies' => 150,
            'file_uploads' => 100,
            'schedule_executions' => 200,
            'cache' => 50,
            'sessions' => 100,
        ];

        return $sizes[$table] ?? 100;
    }

    /**
     * 取得資料保留統計
     */
    public function getRetentionStats(): array
    {
        $stats = [];

        foreach (self::RETENTION_POLICIES as $table => $policy) {
            $totalCount = DB::table($table)->count();
            $expiredCount = DB::table($table)
                ->where('created_at', '<', now()->subDays($policy['retention_days']))
                ->count();
            
            $stats[$table] = [
                'total_records' => $totalCount,
                'expired_records' => $expiredCount,
                'retention_days' => $policy['retention_days'],
                'priority' => $policy['priority'],
                'archive_before_delete' => $policy['archive_before_delete'],
            ];
        }

        return $stats;
    }

    /**
     * 取得資料庫大小統計
     */
    public function getDatabaseStats(): array
    {
        $stats = [];
        
        foreach (self::RETENTION_POLICIES as $table => $policy) {
            $count = DB::table($table)->count();
            $stats[$table] = [
                'record_count' => $count,
                'estimated_size' => $count * $this->estimateRecordSize($table),
            ];
        }

        return $stats;
    }
}
