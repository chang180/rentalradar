<?php

namespace App\Console\Commands;

use App\Services\GovernmentDataDownloadService;
use App\Services\DataParserService;
use App\Services\DataValidationService;
use Illuminate\Console\Command;

class TestGovernmentDataSystem extends Command
{
    protected $signature = 'government:test {--full : 執行完整測試}';

    protected $description = '測試政府資料下載系統';

    public function __construct(
        private GovernmentDataDownloadService $downloadService,
        private DataParserService $parserService,
        private DataValidationService $validationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info("🧪 開始測試政府資料下載系統...");
        $this->newLine();

        $testResults = [
            'download' => false,
            'parsing' => false,
            'validation' => false,
            'database' => false
        ];

        // 測試 1: 下載功能
        $this->info("📥 測試 1: 下載功能");
        try {
            $downloadResult = $this->downloadService->downloadRentalData('csv');
            if ($downloadResult['success']) {
                $this->info("✅ 下載測試成功");
                $this->info("📁 檔案: {$downloadResult['filename']}");
                $this->info("📊 大小: " . $this->formatBytes($downloadResult['file_size']));
                $testResults['download'] = true;
            } else {
                $this->error("❌ 下載測試失敗: {$downloadResult['error']}");
            }
        } catch (\Exception $e) {
            $this->error("❌ 下載測試異常: {$e->getMessage()}");
        }

        $this->newLine();

        // 測試 2: 解析功能
        if ($testResults['download']) {
            $this->info("🔍 測試 2: 解析功能");
            try {
                $parseResult = $this->parserService->parseCsvData($downloadResult['file_path']);
                if ($parseResult['success']) {
                    $this->info("✅ 解析測試成功");
                    $this->info("📊 處理: {$parseResult['processed_count']} 筆");
                    $this->info("❌ 錯誤: {$parseResult['error_count']} 筆");
                    $testResults['parsing'] = true;
                } else {
                    $this->error("❌ 解析測試失敗: {$parseResult['error']}");
                }
            } catch (\Exception $e) {
                $this->error("❌ 解析測試異常: {$e->getMessage()}");
            }
        }

        $this->newLine();

        // 測試 3: 驗證功能
        if ($testResults['parsing'] && $this->option('full')) {
            $this->info("🔍 測試 3: 驗證功能");
            try {
                $validationResult = $this->validationService->validateRentalData($parseResult['data']);
                $this->info("✅ 驗證測試成功");
                $this->info("📊 有效記錄: {$validationResult['valid_records']} 筆");
                $this->info("❌ 無效記錄: {$validationResult['invalid_records']} 筆");
                $this->info("📈 成功率: {$validationResult['success_rate']}%");
                $testResults['validation'] = true;
            } catch (\Exception $e) {
                $this->error("❌ 驗證測試異常: {$e->getMessage()}");
            }
        }

        $this->newLine();

        // 測試 4: 資料庫功能
        if ($testResults['parsing'] && $this->option('full')) {
            $this->info("💾 測試 4: 資料庫功能");
            try {
                // 只儲存前 10 筆資料進行測試
                $testData = array_slice($parseResult['data'], 0, 10);
                $saveResult = $this->parserService->saveToDatabase($testData);
                
                if ($saveResult['success']) {
                    $this->info("✅ 資料庫測試成功");
                    $this->info("💾 儲存: {$saveResult['saved_count']} 筆");
                    $this->info("❌ 錯誤: {$saveResult['error_count']} 筆");
                    $testResults['database'] = true;
                } else {
                    $this->error("❌ 資料庫測試失敗");
                }
            } catch (\Exception $e) {
                $this->error("❌ 資料庫測試異常: {$e->getMessage()}");
            }
        }

        $this->newLine();

        // 測試結果總結
        $this->info("📊 測試結果總結:");
        $this->info("📥 下載功能: " . ($testResults['download'] ? '✅ 通過' : '❌ 失敗'));
        $this->info("🔍 解析功能: " . ($testResults['parsing'] ? '✅ 通過' : '❌ 失敗'));
        $this->info("🔍 驗證功能: " . ($testResults['validation'] ? '✅ 通過' : '❌ 失敗'));
        $this->info("💾 資料庫功能: " . ($testResults['database'] ? '✅ 通過' : '❌ 失敗'));

        $passedTests = array_sum($testResults);
        $totalTests = count($testResults);
        $successRate = round(($passedTests / $totalTests) * 100, 2);

        $this->newLine();
        $this->info("📈 總體成功率: {$successRate}% ({$passedTests}/{$totalTests})");

        if ($successRate >= 75) {
            $this->info("🎉 系統測試通過！");
            return self::SUCCESS;
        } else {
            $this->error("❌ 系統測試失敗，請檢查錯誤訊息");
            return self::FAILURE;
        }
    }

    /**
     * 格式化位元組大小
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
