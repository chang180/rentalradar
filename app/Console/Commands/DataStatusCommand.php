<?php

namespace App\Console\Commands;

use App\Services\GovernmentDataDownloadService;
use App\Models\Property;
use Illuminate\Console\Command;

class DataStatusCommand extends Command
{
    protected $signature = 'data:status {--detailed : 顯示詳細資訊}';

    protected $description = '檢查政府資料下載和處理狀態';

    public function __construct(
        private GovernmentDataDownloadService $downloadService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info("📊 政府資料狀態檢查");
        $this->newLine();

        // 檢查下載狀態
        $this->info("📥 下載狀態:");
        $downloadStatus = $this->downloadService->checkDownloadStatus();
        
        if ($downloadStatus['has_data']) {
            $this->info("✅ 最新檔案: {$downloadStatus['latest_file']}");
            $this->info("📊 檔案大小: " . $this->formatBytes($downloadStatus['file_size']));
            $this->info("📅 最後修改: {$downloadStatus['last_modified']}");
            $this->info("⏰ 檔案年齡: {$downloadStatus['age_hours']} 小時");
        } else {
            $this->warn("❌ 沒有找到政府資料檔案");
        }

        $this->newLine();

        // 檢查下載統計
        $this->info("📈 下載統計:");
        $stats = $this->downloadService->getDownloadStats();
        $this->info("📁 總檔案數: {$stats['total_files']}");
        $this->info("📊 總大小: " . $this->formatBytes($stats['total_size']));
        $this->info("📈 平均大小: " . $this->formatBytes($stats['average_size']));

        if (!empty($stats['formats'])) {
            $this->info("📋 格式分布:");
            foreach ($stats['formats'] as $format => $count) {
                $this->info("  {$format}: {$count} 個檔案");
            }
        }

        $this->newLine();

        // 檢查資料庫狀態
        $this->info("💾 資料庫狀態:");
        $totalProperties = Property::count();
        $geocodedProperties = Property::where('is_geocoded', true)->count();
        $recentProperties = Property::where('created_at', '>=', now()->subDays(7))->count();

        $this->info("📊 總物件數: {$totalProperties}");
        $this->info("📍 已地理編碼: {$geocodedProperties} (" . round(($geocodedProperties / max(1, $totalProperties)) * 100, 2) . "%)");
        $this->info("🆕 近7天新增: {$recentProperties}");

        if ($this->option('detailed')) {
            $this->newLine();
            $this->info("🔍 詳細資訊:");

            // 行政區分布
            $districtStats = Property::selectRaw('district, COUNT(*) as count')
                ->groupBy('district')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            $this->info("🏘️ 行政區分布 (前10名):");
            foreach ($districtStats as $district) {
                $this->info("  {$district->district}: {$district->count} 筆");
            }

            // 建物型態分布
            $buildingTypeStats = Property::selectRaw('building_type, COUNT(*) as count')
                ->groupBy('building_type')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            $this->info("🏢 建物型態分布 (前5名):");
            foreach ($buildingTypeStats as $type) {
                $this->info("  {$type->building_type}: {$type->count} 筆");
            }

            // 價格統計
            $priceStats = Property::selectRaw('
                AVG(rent_per_month) as avg_rent,
                MIN(rent_per_month) as min_rent,
                MAX(rent_per_month) as max_rent,
                AVG(total_floor_area) as avg_area
            ')->first();

            $this->info("💰 價格統計:");
            $this->info("  平均租金: " . number_format($priceStats->avg_rent, 0) . " 元/月");
            $this->info("  最低租金: " . number_format($priceStats->min_rent, 0) . " 元/月");
            $this->info("  最高租金: " . number_format($priceStats->max_rent, 0) . " 元/月");
            $this->info("  平均面積: " . round($priceStats->avg_area, 2) . " 平方公尺");
        }

        $this->newLine();
        $this->info("✅ 狀態檢查完成!");

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
