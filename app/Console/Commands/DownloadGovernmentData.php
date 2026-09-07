<?php

namespace App\Console\Commands;

use App\Services\DataParserService;
use App\Services\GovernmentDataDownloadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DownloadGovernmentData extends Command
{
    protected $signature = 'government:download
                            {--format=csv : 資料格式 (csv 或 xml，內容皆為 ZIP)}
                            {--parse : 下載後立即解析資料}
                            {--save : 解析後儲存到資料庫}
                            {--cleanup : 清理舊檔案}
                            {--skip-if-unchanged : 若內容與上次成功處理的版本相同則跳過解析與儲存，適合每日排程}';

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

        $this->info('🚀 開始下載政府資料...');
        $this->info("📋 格式: {$format}");
        $this->info('🔧 解析: '.($shouldParse ? '是' : '否'));
        $this->info('💾 儲存: '.($shouldSave ? '是' : '否'));
        $this->info('🧹 清理: '.($shouldCleanup ? '是' : '否'));

        // 清理舊檔案
        if ($shouldCleanup) {
            $this->info('🧹 清理舊檔案...');
            $cleanupResult = $this->downloadService->cleanupOldFiles();
            $this->info("✅ 清理完成: 刪除 {$cleanupResult['deleted_count']} 個檔案 ({$cleanupResult['deleted_size']} bytes)");
        }

        // 下載資料
        $this->info('📥 正在下載資料...');
        $downloadResult = $this->downloadService->downloadRentalData($format);

        if (! $downloadResult['success']) {
            $this->error("❌ 下載失敗: {$downloadResult['error']}");

            return self::FAILURE;
        }

        $this->info('✅ 下載成功!');
        $this->info("📁 檔案: {$downloadResult['filename']}");
        $this->info('📊 大小: '.$this->formatBytes($downloadResult['file_size']));
        $this->info("⏱️ 時間: {$downloadResult['download_time']} 秒");
        $this->info("🔄 嘗試: {$downloadResult['attempts']} 次");

        // 內容簽章比對：官方每月僅在 1、11、21 日發布新資料，
        // 每日排程若內容跟上次成功處理的版本相同就直接跳過，避免每天重複解析、寫入資料庫
        $cacheKey = "government_data:last_signature:{$format}";
        $signature = $this->downloadService->fileSignature($downloadResult['file_path']);

        if ($this->option('skip-if-unchanged') && Cache::get($cacheKey) === $signature) {
            $this->info('ℹ️ 資料內容與上次成功處理的版本相同，跳過解析與儲存。');
            Storage::delete($downloadResult['file_path']);

            return self::SUCCESS;
        }

        // 解析資料（內容一律為 ZIP，統一走 parseZipData）
        if ($shouldParse) {
            $this->info('🔍 開始解析資料...');

            $parseResult = $this->parserService->parseZipData($downloadResult['file_path']);

            if (! $parseResult['success']) {
                $this->error("❌ 解析失敗: {$parseResult['error']}");

                return self::FAILURE;
            }

            $this->info('✅ 解析成功!');
            $this->info("📊 處理: {$parseResult['processed_count']} 筆");
            $this->info("❌ 錯誤: {$parseResult['error_count']} 筆");

            if (isset($parseResult['csv_files_count'])) {
                $this->info("📁 CSV 檔案數: {$parseResult['csv_files_count']} 個");
            }

            $total = $parseResult['processed_count'] + $parseResult['error_count'];
            if ($total > 0) {
                $successRate = round(($parseResult['processed_count'] / $total) * 100, 2);
                $this->info("📈 成功率: {$successRate}%");
            }

            // 儲存到資料庫
            if ($shouldSave && ! empty($parseResult['data'])) {
                $this->info('💾 儲存到資料庫...');
                $saveResult = $this->parserService->saveToDatabase($parseResult['data']);

                if ($saveResult['success']) {
                    $this->info('✅ 儲存成功!');
                    $this->info("💾 儲存: {$saveResult['saved_count']} 筆");
                    $this->info("❌ 錯誤: {$saveResult['error_count']} 筆");
                } else {
                    $this->error('❌ 儲存失敗');

                    return self::FAILURE;
                }
            }

            // 這一版內容已成功解析（並視選項儲存），記住簽章供下次比對
            Cache::put($cacheKey, $signature, now()->addDays(45));
        }

        // 顯示統計資訊
        $this->info('📊 下載統計:');
        $stats = $this->downloadService->getDownloadStats();
        $this->info("📁 總檔案數: {$stats['total_files']}");
        $this->info('📊 總大小: '.$this->formatBytes($stats['total_size']));
        $this->info('📈 平均大小: '.$this->formatBytes($stats['average_size']));

        if (! empty($stats['formats'])) {
            $this->info('📋 格式分布:');
            foreach ($stats['formats'] as $format => $count) {
                $this->info("  {$format}: {$count} 個檔案");
            }
        }

        $this->info('🎉 任務完成!');

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

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
