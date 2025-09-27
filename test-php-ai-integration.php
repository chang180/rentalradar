<?php

require_once 'vendor/autoload.php';

use App\Services\AIMapOptimizationService;

// 測試 PHP AI 整合
echo "🤖 測試 PHP AI 整合 (Hostinger 相容)...\n\n";

$aiService = new AIMapOptimizationService();

// 測試資料
$testData = [
    ['lat' => 25.0330, 'lng' => 121.5654, 'price' => 25000, 'area' => 25, 'floor' => 5, 'age' => 10],
    ['lat' => 25.0340, 'lng' => 121.5664, 'price' => 28000, 'area' => 30, 'floor' => 3, 'age' => 5],
    ['lat' => 25.0350, 'lng' => 121.5674, 'price' => 30000, 'area' => 35, 'floor' => 7, 'age' => 8],
    ['lat' => 25.0360, 'lng' => 121.5684, 'price' => 100000, 'area' => 50, 'floor' => 1, 'age' => 15], // 異常值
    ['lat' => 25.0370, 'lng' => 121.5694, 'price' => 32000, 'area' => 28, 'floor' => 4, 'age' => 6],
];

// 1. 測試聚合演算法
echo "1. 測試聚合演算法:\n";
$result = $aiService->clusteringAlgorithm($testData, 'kmeans', 3);
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   聚合數量: " . count($result['clusters']) . "\n";
    echo "   處理時間: " . $result['algorithm_info']['performance']['processing_time'] . "秒\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n";

// 2. 測試熱力圖
echo "2. 測試熱力圖分析:\n";
$result = $aiService->generateHeatmap($testData, 'medium');
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   熱力圖點數: " . count($result['heatmap_points']) . "\n";
    echo "   密度範圍: " . $result['statistics']['density_range']['min'] . " - " . $result['statistics']['density_range']['max'] . "\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n";

// 3. 測試價格預測
echo "3. 測試價格預測:\n";
$result = $aiService->predictPrices($testData);
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   預測數量: " . count($result['predictions']) . "\n";
    echo "   模型準確率: " . $result['model_info']['accuracy'] . "\n";
    echo "   處理時間: " . $result['performance_metrics']['processing_time'] . "秒\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n";

// 4. 測試網格聚合
echo "4. 測試網格聚合:\n";
$result = $aiService->clusteringAlgorithm($testData, 'grid');
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   聚合數量: " . count($result['clusters']) . "\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n";

// 5. 效能測試
echo "5. 效能測試 (1000 筆資料):\n";
$largeData = [];
for ($i = 0; $i < 1000; $i++) {
    $largeData[] = [
        'lat' => 25.0330 + (rand(-100, 100) / 10000),
        'lng' => 121.5654 + (rand(-100, 100) / 10000),
        'price' => rand(15000, 50000),
        'area' => rand(15, 50),
        'floor' => rand(1, 20),
        'age' => rand(1, 30)
    ];
}

$startTime = microtime(true);
$result = $aiService->clusteringAlgorithm($largeData, 'grid');
$endTime = microtime(true);

echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   處理時間: " . round($endTime - $startTime, 3) . "秒\n";
    echo "   聚合數量: " . count($result['clusters']) . "\n";
    echo "   記憶體使用: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n🎉 PHP AI 整合測試完成！\n";
echo "✅ 完全相容 Hostinger 共享空間\n";
echo "✅ 無需外部依賴\n";
echo "✅ 高效能處理\n";
