<?php

namespace App\Console\Commands;

use App\Services\GovernmentDataDownloadService;
use App\Services\DataParserService;
use Illuminate\Console\Command;

class DownloadGovernmentData extends Command
{
    protected $signature = 'government:download 
                            {--format=csv : 資料格式 (csv 或 xml)}
                            {--parse : 下載後立即解析資料}
                            {--save : 解析後儲存到資料庫}
                            {--cleanup : 清理舊檔案}';

    protected $description = '下載政府租賃實價登錄資料';

    public function __construct(
        private GovernmentDataDownloadService $downloadService,
        private DataParserService $parserService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');
        $shouldParse = $this->option('parse');
        $shouldSave = $this->option('save');
        $shouldCleanup = $this->option('cleanup');

        $this->info("🚀 開始下載政府資料...");
        $this->info("📋 格式: {$format}");
        $this->info("🔧 解析: " . ($shouldParse ? '是' : '否'));
        $this->info("💾 儲存: " . ($shouldSave ? '是' : '否'));
        $this->info("🧹 清理: " . ($shouldCleanup ? '是' : '否'));

        // 清理舊檔案
        if ($shouldCleanup) {
            $this->info("🧹 清理舊檔案...");
            $cleanupResult = $this->downloadService->cleanupOldFiles();
            $this->info("✅ 清理完成: 刪除 {$cleanupResult['deleted_count']} 個檔案 ({$cleanupResult['deleted_size']} bytes)");
        }

        // 下載資料
        $this->info("📥 正在下載資料...");
        $downloadResult = $this->downloadService->downloadRentalData($format);

        if (!$downloadResult['success']) {
            $this->error("❌ 下載失敗: {$downloadResult['error']}");
            return self::FAILURE;
        }

        $this->info("✅ 下載成功!");
        $this->info("📁 檔案: {$downloadResult['filename']}");
        $this->info("📊 大小: " . $this->formatBytes($downloadResult['file_size']));
        $this->info("⏱️ 時間: {$downloadResult['download_time']} 秒");
        $this->info("🔄 嘗試: {$downloadResult['attempts']} 次");

        // 解析資料
        if ($shouldParse) {
            $this->info("🔍 開始解析資料...");
            $parseResult = $this->parserService->parseCsvData($downloadResult['file_path']);

            if (!$parseResult['success']) {
                $this->error("❌ 解析失敗: {$parseResult['error']}");
                return self::FAILURE;
            }

            $this->info("✅ 解析成功!");
            $this->info("📊 處理: {$parseResult['processed_count']} 筆");
            $this->info("❌ 錯誤: {$parseResult['error_count']} 筆");
            $this->info("📈 成功率: " . round(($parseResult['processed_count'] / ($parseResult['processed_count'] + $parseResult['error_count'])) * 100, 2) . "%");

            // 儲存到資料庫
            if ($shouldSave && !empty($parseResult['data'])) {
                $this->info("💾 儲存到資料庫...");
                $saveResult = $this->parserService->saveToDatabase($parseResult['data']);

                if ($saveResult['success']) {
                    $this->info("✅ 儲存成功!");
                    $this->info("💾 儲存: {$saveResult['saved_count']} 筆");
                    $this->info("❌ 錯誤: {$saveResult['error_count']} 筆");
                } else {
                    $this->error("❌ 儲存失敗");
                    return self::FAILURE;
                }
            }
        }

        // 顯示統計資訊
        $this->info("📊 下載統計:");
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

        $this->info("🎉 任務完成!");
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
