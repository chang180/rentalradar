<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class GovernmentDataDownloadService
{
    private string $baseUrl = 'https://data.moi.gov.tw/MoiOD/System/DownloadFile.aspx';
    private string $dataId = 'F85D101E-1453-49B2-892D-36234CF9303D';
    private int $maxRetries = 5;
    private int $retryDelay = 10; // seconds

    /**
     * 檢查服務器連接性
     */
    private function checkConnectivity(): bool
    {
        try {
            $response = Http::timeout(10)->get('https://data.moi.gov.tw/');
            return $response->status() < 500;
        } catch (\Exception $e) {
            Log::warning('Government server connectivity check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 下載政府租賃實價登錄資料
     */
    public function downloadRentalData(string $format = 'csv'): array
    {
        // 先檢查連接性
        if (!$this->checkConnectivity()) {
            $error = 'Government data server is unreachable. This may be due to geographic restrictions or network issues.';
            Log::error($error);

            return [
                'success' => false,
                'error' => $error,
                'attempts' => 0,
                'failed_at' => now()->toISOString(),
                'suggestion' => 'Try using a VPN with Taiwan location or contact your hosting provider about network access to Taiwan government servers.'
            ];
        }

        $startTime = microtime(true);
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->maxRetries) {
            try {
                $attempts++;
                Log::info("開始下載政府資料 (嘗試 {$attempts}/{$this->maxRetries})", [
                    'format' => $format,
                    'timestamp' => now()->toISOString()
                ]);

                $response = $this->makeDownloadRequest($format);
                
                if ($response->successful()) {
                    $filename = $this->generateFilename($format);
                    $filePath = "government-data/{$filename}";
                    
                    // 儲存檔案
                    Storage::put($filePath, $response->body());
                    
                    $fileSize = Storage::size($filePath);
                    $downloadTime = microtime(true) - $startTime;
                    
                    Log::info("政府資料下載成功", [
                        'filename' => $filename,
                        'file_size' => $fileSize,
                        'download_time' => round($downloadTime, 2),
                        'format' => $format
                    ]);

                    return [
                        'success' => true,
                        'filename' => $filename,
                        'file_path' => $filePath,
                        'file_size' => $fileSize,
                        'download_time' => round($downloadTime, 2),
                        'format' => $format,
                        'attempts' => $attempts,
                        'downloaded_at' => now()->toISOString()
                    ];
                } else {
                    $lastError = "HTTP {$response->status()}: {$response->body()}";
                    Log::warning("下載失敗", [
                        'status' => $response->status(),
                        'attempt' => $attempts,
                        'error' => $lastError
                    ]);
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("下載過程中發生錯誤", [
                    'attempt' => $attempts,
                    'error' => $lastError,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // 如果不是最後一次嘗試，等待後重試
            if ($attempts < $this->maxRetries) {
                sleep($this->retryDelay);
            }
        }

        // 所有嘗試都失敗了
        Log::error("政府資料下載最終失敗", [
            'attempts' => $attempts,
            'last_error' => $lastError,
            'format' => $format
        ]);

        return [
            'success' => false,
            'error' => $lastError,
            'attempts' => $attempts,
            'failed_at' => now()->toISOString()
        ];
    }

    /**
     * 執行下載請求
     */
    private function makeDownloadRequest(string $format): \Illuminate\Http\Client\Response
    {
        $params = [
            'DATA' => $this->dataId
        ];

        if ($format === 'xml') {
            $params['format'] = 'xml';
        }

        return Http::withHeaders([
            'User-Agent' => 'RentalRadar/1.0 (taiwan.rental.radar@gmail.com)',
            'Accept' => $format === 'xml' ? 'application/xml' : 'text/csv',
        ])
        ->timeout(180)
        ->connectTimeout(60)
        ->retry(2, 5000)
        ->get($this->baseUrl, $params);
    }

    /**
     * 生成檔案名稱
     */
    private function generateFilename(string $format): string
    {
        $date = now()->format('Y-m-d');
        $timestamp = now()->format('H-i-s');
        return "rental-data-{$date}-{$timestamp}.{$format}";
    }

    /**
     * 檢查下載狀態
     */
    public function checkDownloadStatus(): array
    {
        $files = Storage::files('government-data');
        $latestFile = null;
        $latestTime = 0;

        foreach ($files as $file) {
            $time = Storage::lastModified($file);
            if ($time > $latestTime) {
                $latestTime = $time;
                $latestFile = $file;
            }
        }

        if ($latestFile) {
            return [
                'has_data' => true,
                'latest_file' => $latestFile,
                'file_size' => Storage::size($latestFile),
                'last_modified' => Carbon::createFromTimestamp($latestTime)->toISOString(),
                'age_hours' => Carbon::createFromTimestamp($latestTime)->diffInHours(now())
            ];
        }

        return [
            'has_data' => false,
            'message' => '沒有找到政府資料檔案'
        ];
    }

    /**
     * 清理舊檔案
     */
    public function cleanupOldFiles(int $daysToKeep = 7): array
    {
        $files = Storage::files('government-data');
        $deletedCount = 0;
        $deletedSize = 0;
        $cutoffTime = now()->subDays($daysToKeep)->timestamp;

        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            if ($lastModified < $cutoffTime) {
                $fileSize = Storage::size($file);
                Storage::delete($file);
                $deletedCount++;
                $deletedSize += $fileSize;
            }
        }

        Log::info("清理舊檔案完成", [
            'deleted_count' => $deletedCount,
            'deleted_size' => $deletedSize,
            'days_kept' => $daysToKeep
        ]);

        return [
            'deleted_count' => $deletedCount,
            'deleted_size' => $deletedSize,
            'days_kept' => $daysToKeep
        ];
    }

    /**
     * 獲取下載統計
     */
    public function getDownloadStats(): array
    {
        $files = Storage::files('government-data');
        $totalSize = 0;
        $fileCount = count($files);
        $formats = [];

        foreach ($files as $file) {
            $totalSize += Storage::size($file);
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $formats[$extension] = ($formats[$extension] ?? 0) + 1;
        }

        return [
            'total_files' => $fileCount,
            'total_size' => $totalSize,
            'formats' => $formats,
            'average_size' => $fileCount > 0 ? round($totalSize / $fileCount) : 0
        ];
    }
}
