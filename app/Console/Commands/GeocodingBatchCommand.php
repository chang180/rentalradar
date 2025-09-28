<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GeocodingBatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geocoding:batch {--limit=100 : 每次處理的數量} {--delay=1 : 請求間隔秒數}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '批次處理地理編碼';

    /**
     * Execute the console command.
     */
    public function handle(GeocodingService $geocodingService)
    {
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');
        
        $this->info("開始批次地理編碼處理...");
        $this->info("處理數量: {$limit}, 請求間隔: {$delay}秒");
        
        // 取得未地理編碼的記錄
        $properties = Property::where('is_geocoded', false)
            ->whereNotNull('city')
            ->whereNotNull('district')
            ->limit($limit)
            ->get();
            
        if ($properties->isEmpty()) {
            $this->info('沒有需要地理編碼的記錄');
            return;
        }
        
        $this->info("找到 {$properties->count()} 筆需要地理編碼的記錄");
        
        $successCount = 0;
        $failCount = 0;
        
        $progressBar = $this->output->createProgressBar($properties->count());
        $progressBar->start();
        
        foreach ($properties as $property) {
            try {
                // 建立地址字串
                $address = $property->city . $property->district;
                
                // 執行地理編碼
                $result = $geocodingService->geocodeAddress($address);
                
                if ($result) {
                    $property->update([
                        'latitude' => $result['latitude'],
                        'longitude' => $result['longitude'],
                        'is_geocoded' => true,
                    ]);
                    $successCount++;
                } else {
                    $failCount++;
                }
                
                $progressBar->advance();
                
                // 延遲避免 API 限制
                if ($delay > 0) {
                    sleep($delay);
                }
                
            } catch (\Exception $e) {
                $failCount++;
                Log::error('地理編碼失敗', [
                    'property_id' => $property->id,
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
                $progressBar->advance();
            }
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->info("地理編碼處理完成!");
        $this->info("成功: {$successCount} 筆");
        $this->info("失敗: {$failCount} 筆");
        
        // 記錄執行結果
        Log::info('地理編碼批次處理完成', [
            'total_processed' => $properties->count(),
            'success_count' => $successCount,
            'fail_count' => $failCount,
        ]);
    }
}
