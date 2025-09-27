<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GovernmentDataDownloadService;
use App\Services\DataParserService;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScheduleDataUpdates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'data:update 
                            {--force : 強制更新，忽略時間限制}
                            {--geocode : 執行地理編碼}
                            {--limit=1000 : 地理編碼限制數量}';

    /**
     * The console command description.
     */
    protected $description = '執行定期資料更新任務';

    private GovernmentDataDownloadService $downloadService;
    private DataParserService $parserService;
    private GeocodingService $geocodingService;

    public function __construct(
        GovernmentDataDownloadService $downloadService,
        DataParserService $parserService,
        GeocodingService $geocodingService
    ) {
        parent::__construct();
        $this->downloadService = $downloadService;
        $this->parserService = $parserService;
        $this->geocodingService = $geocodingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('開始執行資料更新任務...');
        
        try {
            $startTime = microtime(true);
            
            // 檢查是否在更新時間窗口內
            if (!$this->option('force') && !$this->isUpdateTimeWindow()) {
                $this->warn('不在更新時間窗口內，使用 --force 強制更新');
                return Command::SUCCESS;
            }
            
            // 檢查是否有其他更新任務正在運行
            if ($this->isUpdateInProgress()) {
                $this->warn('資料更新任務正在運行中，跳過此次更新');
                return Command::SUCCESS;
            }
            
            // 標記更新開始
            $this->markUpdateInProgress();
            
            // 執行資料下載
            $downloadResult = $this->executeDataDownload();
            
            // 執行資料解析
            $parseResult = $this->executeDataParsing();
            
            // 執行地理編碼（如果啟用）
            $geocodeResult = null;
            if ($this->option('geocode')) {
                $geocodeResult = $this->executeGeocoding();
            }
            
            // 計算執行時間
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // 顯示結果
            $this->displayResults($downloadResult, $parseResult, $geocodeResult, $executionTime);
            
            // 記錄更新結果
            $this->logUpdateResult($downloadResult, $parseResult, $geocodeResult, $executionTime);
            
            // 清除更新進行中標記
            $this->clearUpdateInProgress();
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('資料更新失敗: ' . $e->getMessage());
            Log::error('Data update failed: ' . $e->getMessage());
            $this->clearUpdateInProgress();
            return Command::FAILURE;
        }
    }

    /**
     * 執行資料下載
     */
    private function executeDataDownload(): array
    {
        $this->info('開始下載政府資料...');
        
        try {
            // 下載 CSV 格式資料
            $csvResult = $this->downloadService->downloadRentalData('csv');
            
            // 下載 XML 格式資料
            $xmlResult = $this->downloadService->downloadRentalData('xml');
            
            // 下載 ZIP 格式資料
            $zipResult = $this->downloadService->downloadRentalData('zip');
            
            $totalSuccessful = 0;
            $totalFailed = 0;
            
            if ($csvResult['success']) $totalSuccessful++; else $totalFailed++;
            if ($xmlResult['success']) $totalSuccessful++; else $totalFailed++;
            if ($zipResult['success']) $totalSuccessful++; else $totalFailed++;
            
            $result = [
                'successful' => $totalSuccessful,
                'failed' => $totalFailed,
                'total' => $totalSuccessful + $totalFailed,
                'details' => [
                    'csv' => $csvResult,
                    'xml' => $xmlResult,
                    'zip' => $zipResult
                ]
            ];
            
            $this->info("下載完成:");
            $this->line("  - 成功: {$result['successful']}");
            $this->line("  - 失敗: {$result['failed']}");
            $this->line("  - 總計: {$result['total']}");
            
            return $result;
        } catch (\Exception $e) {
            $this->error("資料下載失敗: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 執行資料解析
     */
    private function executeDataParsing(): array
    {
        $this->info('開始解析資料...');
        
        try {
            $totalFiles = 0;
            $totalSuccessful = 0;
            $totalFailed = 0;
            $totalRecords = 0;
            
            // 解析 CSV 檔案
            $csvFiles = \Storage::files('government-data');
            foreach ($csvFiles as $file) {
                if (str_ends_with($file, '.csv')) {
                    $totalFiles++;
                    try {
                        $result = $this->parserService->parseCsvData($file);
                        $totalSuccessful += $result['successful_records'] ?? 0;
                        $totalFailed += $result['failed_records'] ?? 0;
                        $totalRecords += $result['total_records'] ?? 0;
                    } catch (\Exception $e) {
                        $this->warn("CSV 檔案解析失敗: {$file} - " . $e->getMessage());
                    }
                }
            }
            
            // 解析 XML 檔案
            foreach ($csvFiles as $file) {
                if (str_ends_with($file, '.xml')) {
                    $totalFiles++;
                    try {
                        $result = $this->parserService->parseXmlData($file);
                        $totalSuccessful += $result['successful_records'] ?? 0;
                        $totalFailed += $result['failed_records'] ?? 0;
                        $totalRecords += $result['total_records'] ?? 0;
                    } catch (\Exception $e) {
                        $this->warn("XML 檔案解析失敗: {$file} - " . $e->getMessage());
                    }
                }
            }
            
            // 解析 ZIP 檔案
            foreach ($csvFiles as $file) {
                if (str_ends_with($file, '.zip')) {
                    $totalFiles++;
                    try {
                        $result = $this->parserService->parseZipData($file);
                        $totalSuccessful += $result['successful_records'] ?? 0;
                        $totalFailed += $result['failed_records'] ?? 0;
                        $totalRecords += $result['total_records'] ?? 0;
                    } catch (\Exception $e) {
                        $this->warn("ZIP 檔案解析失敗: {$file} - " . $e->getMessage());
                    }
                }
            }
            
            $result = [
                'files_processed' => $totalFiles,
                'successful_records' => $totalSuccessful,
                'failed_records' => $totalFailed,
                'total_records' => $totalRecords
            ];
            
            $this->info("解析完成:");
            $this->line("  - 處理檔案: {$result['files_processed']}");
            $this->line("  - 成功記錄: {$result['successful_records']}");
            $this->line("  - 失敗記錄: {$result['failed_records']}");
            $this->line("  - 總記錄數: {$result['total_records']}");
            
            return $result;
        } catch (\Exception $e) {
            $this->error("資料解析失敗: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 執行地理編碼
     */
    private function executeGeocoding(): array
    {
        $this->info('開始地理編碼...');
        
        try {
            $limit = (int) $this->option('limit');
            
            // 獲取未編碼的屬性
            $properties = \App\Models\Property::where('is_geocoded', false)
                ->whereNotNull('address')
                ->limit($limit)
                ->get();
            
            $processed = 0;
            $successful = 0;
            $failed = 0;
            
            foreach ($properties as $property) {
                $processed++;
                try {
                    $success = $this->geocodingService->geocodeProperty($property);
                    if ($success) {
                        $successful++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->warn("屬性 {$property->id} 地理編碼失敗: " . $e->getMessage());
                }
            }
            
            $successRate = $processed > 0 ? round(($successful / $processed) * 100, 2) : 0;
            
            $result = [
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => $successRate
            ];
            
            $this->info("地理編碼完成:");
            $this->line("  - 處理數量: {$result['processed']}");
            $this->line("  - 成功編碼: {$result['successful']}");
            $this->line("  - 失敗編碼: {$result['failed']}");
            $this->line("  - 成功率: {$result['success_rate']}%");
            
            return $result;
        } catch (\Exception $e) {
            $this->error("地理編碼失敗: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 顯示結果
     */
    private function displayResults(array $downloadResult, array $parseResult, ?array $geocodeResult, float $executionTime): void
    {
        $this->info('=== 資料更新完成 ===');
        $this->line("執行時間: {$executionTime}ms");
        $this->line("下載成功: {$downloadResult['successful']}/{$downloadResult['total']}");
        $this->line("解析成功: {$parseResult['successful_records']}/{$parseResult['total_records']}");
        
        if ($geocodeResult) {
            $this->line("地理編碼成功: {$geocodeResult['successful']}/{$geocodeResult['processed']}");
        }
        
        // 計算整體成功率
        $overallSuccess = $this->calculateOverallSuccess($downloadResult, $parseResult, $geocodeResult);
        $this->line("整體成功率: {$overallSuccess}%");
    }

    /**
     * 計算整體成功率
     */
    private function calculateOverallSuccess(array $downloadResult, array $parseResult, ?array $geocodeResult): float
    {
        $totalTasks = 2; // 下載 + 解析
        $successfulTasks = 0;
        
        if ($downloadResult['successful'] > 0) {
            $successfulTasks++;
        }
        
        if ($parseResult['successful_records'] > 0) {
            $successfulTasks++;
        }
        
        if ($geocodeResult && $geocodeResult['successful'] > 0) {
            $totalTasks++;
            $successfulTasks++;
        }
        
        return round(($successfulTasks / $totalTasks) * 100, 2);
    }

    /**
     * 記錄更新結果
     */
    private function logUpdateResult(array $downloadResult, array $parseResult, ?array $geocodeResult, float $executionTime): void
    {
        $logData = [
            'download_result' => $downloadResult,
            'parse_result' => $parseResult,
            'geocode_result' => $geocodeResult,
            'execution_time' => $executionTime,
            'timestamp' => now()->toISOString()
        ];
        
        Log::channel('system')->info('Data update completed', $logData);
        
        // 更新快取統計
        $this->updateUpdateStatistics($downloadResult, $parseResult, $geocodeResult, $executionTime);
    }

    /**
     * 更新統計資料
     */
    private function updateUpdateStatistics(array $downloadResult, array $parseResult, ?array $geocodeResult, float $executionTime): void
    {
        $cacheKey = 'data_update_statistics';
        $statistics = Cache::get($cacheKey, [
            'total_updates' => 0,
            'successful_updates' => 0,
            'failed_updates' => 0,
            'average_execution_time' => 0,
            'last_update' => null,
            'total_records_processed' => 0
        ]);
        
        $statistics['total_updates']++;
        
        // 判斷更新是否成功
        $isSuccessful = $downloadResult['successful'] > 0 && $parseResult['successful_records'] > 0;
        
        if ($isSuccessful) {
            $statistics['successful_updates']++;
        } else {
            $statistics['failed_updates']++;
        }
        
        // 更新平均執行時間
        $totalTime = $statistics['average_execution_time'] * ($statistics['total_updates'] - 1);
        $statistics['average_execution_time'] = ($totalTime + $executionTime) / $statistics['total_updates'];
        
        $statistics['last_update'] = now()->toISOString();
        $statistics['total_records_processed'] += $parseResult['total_records'];
        
        Cache::put($cacheKey, $statistics, 86400); // 保留 24 小時
    }

    /**
     * 檢查是否在更新時間窗口內
     */
    private function isUpdateTimeWindow(): bool
    {
        $currentHour = now()->hour;
        
        // 允許在凌晨 2-6 點更新
        return $currentHour >= 2 && $currentHour <= 6;
    }

    /**
     * 檢查是否有更新任務正在運行
     */
    private function isUpdateInProgress(): bool
    {
        return Cache::has('data_update_in_progress');
    }

    /**
     * 標記更新進行中
     */
    private function markUpdateInProgress(): void
    {
        Cache::put('data_update_in_progress', true, 3600); // 1 小時過期
    }

    /**
     * 清除更新進行中標記
     */
    private function clearUpdateInProgress(): void
    {
        Cache::forget('data_update_in_progress');
    }
}
