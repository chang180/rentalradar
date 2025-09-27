<?php

namespace App\Console\Commands;

use App\Services\GovernmentDataDownloadService;
use App\Services\DataParserService;
use App\Services\DataValidationService;
use App\Models\Property;
use Illuminate\Console\Command;

class GovernmentDataMaintenance extends Command
{
    protected $signature = 'government:maintenance 
                            {--status : 檢查系統狀態}
                            {--cleanup : 清理舊檔案}
                            {--validate : 驗證資料品質}
                            {--geocode : 執行地理編碼}
                            {--full : 執行完整維護}';

    protected $description = '政府資料系統維護命令';

    public function __construct(
        private GovernmentDataDownloadService $downloadService,
        private DataParserService $parserService,
        private DataValidationService $validationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info("🔧 政府資料系統維護");
        $this->newLine();

        if ($this->option('status')) {
            return $this->checkSystemStatus();
        }

        if ($this->option('cleanup')) {
            return $this->cleanupOldFiles();
        }

        if ($this->option('validate')) {
            return $this->validateDataQuality();
        }

        if ($this->option('geocode')) {
            return $this->performGeocoding();
        }

        if ($this->option('full')) {
            return $this->performFullMaintenance();
        }

        $this->error("❌ 請指定維護選項");
        $this->info("可用選項: --status, --cleanup, --validate, --geocode, --full");
        return self::FAILURE;
    }

    /**
     * 檢查系統狀態
     */
    private function checkSystemStatus(): int
    {
        $this->info("📊 檢查系統狀態...");

        // 檢查下載狀態
        $downloadStatus = $this->downloadService->checkDownloadStatus();
        if ($downloadStatus['has_data']) {
            $this->info("✅ 最新檔案: {$downloadStatus['latest_file']}");
            $this->info("📊 檔案大小: " . $this->formatBytes($downloadStatus['file_size']));
            $this->info("📅 最後修改: {$downloadStatus['last_modified']}");
            $this->info("⏰ 檔案年齡: {$downloadStatus['age_hours']} 小時");
        } else {
            $this->warn("❌ 沒有找到政府資料檔案");
        }

        // 檢查資料庫狀態
        $totalProperties = Property::count();
        $geocodedProperties = Property::where('is_geocoded', true)->count();
        $recentProperties = Property::where('created_at', '>=', now()->subDays(7))->count();

        $this->info("💾 資料庫狀態:");
        $this->info("📊 總物件數: {$totalProperties}");
        $this->info("📍 已地理編碼: {$geocodedProperties} (" . round(($geocodedProperties / max(1, $totalProperties)) * 100, 2) . "%)");
        $this->info("🆕 近7天新增: {$recentProperties}");

        // 檢查下載統計
        $stats = $this->downloadService->getDownloadStats();
        $this->info("📈 下載統計:");
        $this->info("📁 總檔案數: {$stats['total_files']}");
        $this->info("📊 總大小: " . $this->formatBytes($stats['total_size']));

        return self::SUCCESS;
    }

    /**
     * 清理舊檔案
     */
    private function cleanupOldFiles(): int
    {
        $this->info("🧹 清理舊檔案...");
        
        $cleanupResult = $this->downloadService->cleanupOldFiles();
        
        $this->info("✅ 清理完成!");
        $this->info("🗑️ 刪除檔案: {$cleanupResult['deleted_count']} 個");
        $this->info("📊 釋放空間: " . $this->formatBytes($cleanupResult['deleted_size']));
        $this->info("📅 保留天數: {$cleanupResult['days_kept']} 天");

        return self::SUCCESS;
    }

    /**
     * 驗證資料品質
     */
    private function validateDataQuality(): int
    {
        $this->info("🔍 驗證資料品質...");

        // 獲取最近的資料
        $recentProperties = Property::where('created_at', '>=', now()->subDays(7))->get();
        
        if ($recentProperties->isEmpty()) {
            $this->warn("⚠️ 沒有找到最近的資料進行驗證");
            return self::SUCCESS;
        }

        $data = $recentProperties->map(function ($property) {
            return [
                'address' => $property->full_address,
                'district' => $property->district,
                'total_price' => $property->rent_per_month,
                'unit_price' => $property->rent_per_month / max(1, $property->total_floor_area),
                'area' => $property->total_floor_area,
                'transaction_date' => $property->rent_date,
            ];
        })->toArray();

        $validationResult = $this->validationService->validateRentalData($data);
        
        $this->info("✅ 驗證完成!");
        $this->info("📊 有效記錄: {$validationResult['valid_records']} 筆");
        $this->info("❌ 無效記錄: {$validationResult['invalid_records']} 筆");
        $this->info("📈 成功率: {$validationResult['success_rate']}%");

        if (!empty($validationResult['warnings'])) {
            $this->warn("⚠️ 警告數量: " . count($validationResult['warnings']));
        }

        if (!empty($validationResult['errors'])) {
            $this->error("❌ 錯誤數量: " . count($validationResult['errors']));
        }

        // 顯示品質報告
        $qualityReport = $this->validationService->checkDataQuality($data);
        $this->info("📊 資料品質評分: {$qualityReport['overall_score']}/100");
        $this->info("  - 完整性: {$qualityReport['completeness_score']}%");
        $this->info("  - 準確性: {$qualityReport['accuracy_score']}%");
        $this->info("  - 一致性: {$qualityReport['consistency_score']}%");

        return self::SUCCESS;
    }

    /**
     * 執行地理編碼
     */
    private function performGeocoding(): int
    {
        $this->info("📍 執行地理編碼...");

        try {
            $this->call('properties:geocode', [
                '--limit' => 50,
                '--force' => false
            ]);

            $this->info("✅ 地理編碼完成!");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ 地理編碼失敗: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * 執行完整維護
     */
    private function performFullMaintenance(): int
    {
        $this->info("🔧 執行完整維護...");
        $this->newLine();

        // 1. 檢查系統狀態
        $this->info("📊 步驟 1: 檢查系統狀態");
        $this->checkSystemStatus();
        $this->newLine();

        // 2. 清理舊檔案
        $this->info("🧹 步驟 2: 清理舊檔案");
        $this->cleanupOldFiles();
        $this->newLine();

        // 3. 驗證資料品質
        $this->info("🔍 步驟 3: 驗證資料品質");
        $this->validateDataQuality();
        $this->newLine();

        // 4. 執行地理編碼
        $this->info("📍 步驟 4: 執行地理編碼");
        $this->performGeocoding();
        $this->newLine();

        $this->info("🎉 完整維護完成!");
        return self::SUCCESS;
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
