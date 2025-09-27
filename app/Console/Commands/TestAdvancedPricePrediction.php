<?php

namespace App\Console\Commands;

use App\Support\AdvancedPricePredictor;
use App\Models\Property;
use Illuminate\Console\Command;

class TestAdvancedPricePrediction extends Command
{
    protected $signature = 'test:advanced-price-prediction {--seed : 先執行資料庫種子}';
    protected $description = '測試進階價格預測功能';

    public function handle(): int
    {
        $this->info("🧪 測試進階價格預測功能");
        $this->newLine();

        // 如果需要，先執行種子資料
        if ($this->option('seed')) {
            $this->info("📊 執行資料庫種子...");
            $this->call('db:seed', ['--class' => 'PropertySeeder']);
            $this->info("✅ 種子資料建立完成");
            $this->newLine();
        }

        // 檢查是否有資料
        $propertyCount = Property::count();
        if ($propertyCount === 0) {
            $this->error("❌ 沒有找到租賃物件資料，請先執行: php artisan db:seed --class=PropertySeeder");
            return self::FAILURE;
        }

        $this->info("📊 找到 {$propertyCount} 筆租賃物件資料");
        $this->newLine();

        // 測試進階價格預測
        $this->testAdvancedPricePrediction();

        return self::SUCCESS;
    }

    private function testAdvancedPricePrediction(): void
    {
        $this->info("🔍 測試進階價格預測功能...");

        $predictor = new AdvancedPricePredictor();
        
        // 獲取一些測試資料
        $properties = Property::limit(10)->get();
        
        if ($properties->isEmpty()) {
            $this->error("❌ 沒有找到測試資料");
            return;
        }

        $this->info("📋 測試資料: {$properties->count()} 筆物件");
        $this->newLine();

        // 測試單一預測
        $this->info("🎯 測試單一價格預測:");
        $property = $properties->first();
        $prediction = $predictor->predict($property->toArray());
        
        $this->line("物件: {$property->full_address}");
        $this->line("實際租金: {$property->rent_per_month} 元");
        $this->line("預測租金: {$prediction['price']} 元");
        $this->line("信心度: " . round($prediction['confidence'] * 100, 1) . "%");
        $this->line("預測結果: " . json_encode($prediction, JSON_UNESCAPED_UNICODE));
        $this->newLine();

        // 測試批量預測
        $this->info("📊 測試批量價格預測:");
        $predictions = $predictor->predictCollection($properties->toArray());
        
        $this->line("預測結果: " . json_encode($predictions, JSON_UNESCAPED_UNICODE));
        $this->newLine();

        $this->info("✅ 進階價格預測測試完成!");
    }
}
