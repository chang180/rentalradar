<?php

require_once 'vendor/autoload.php';

use App\Services\PythonIntegrationService;

// 測試 Python 整合
echo "🐍 測試 Python 整合...\n\n";

$pythonService = new PythonIntegrationService();

// 1. 檢查 Python 環境
echo "1. 檢查 Python 環境:\n";
$status = $pythonService->checkPythonEnvironment();
echo "   可用性: " . ($status['available'] ? '✅' : '❌') . "\n";
if ($status['available']) {
    echo "   版本: " . $status['version'] . "\n";
    echo "   就緒: " . ($status['ready'] ? '✅' : '❌') . "\n";
    if (!empty($status['missing_packages'])) {
        echo "   缺少套件: " . implode(', ', $status['missing_packages']) . "\n";
    }
} else {
    echo "   錯誤: " . $status['error'] . "\n";
}

echo "\n";

// 2. 測試異常值檢測
echo "2. 測試異常值檢測:\n";
$testData = [
    ['lat' => 25.0330, 'lng' => 121.5654, 'price' => 25000],
    ['lat' => 25.0340, 'lng' => 121.5664, 'price' => 28000],
    ['lat' => 25.0350, 'lng' => 121.5674, 'price' => 30000],
    ['lat' => 25.0360, 'lng' => 121.5684, 'price' => 100000], // 異常值
    ['lat' => 25.0370, 'lng' => 121.5694, 'price' => 32000],
];

$result = $pythonService->detectAnomalies($testData, ['method' => 'zscore']);
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   處理時間: " . $result['performance']['execution_time'] . "秒\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n";

// 3. 測試聚合演算法
echo "3. 測試聚合演算法:\n";
$result = $pythonService->optimizeMapData($testData, ['algorithm' => 'kmeans']);
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   聚合數量: " . count($result['data']['clusters']) . "\n";
    echo "   處理時間: " . $result['performance']['execution_time'] . "秒\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n";

// 4. 測試價格預測
echo "4. 測試價格預測:\n";
$predictionData = [
    ['lat' => 25.0330, 'lng' => 121.5654, 'area' => 25, 'floor' => 5, 'age' => 10, 'elevator' => true, 'parking' => false],
    ['lat' => 25.0340, 'lng' => 121.5664, 'area' => 30, 'floor' => 3, 'age' => 5, 'elevator' => true, 'parking' => true],
];

$result = $pythonService->predictPrices($predictionData);
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   預測數量: " . count($result['data']['predictions']) . "\n";
    echo "   模型準確率: " . $result['data']['model_info']['accuracy'] . "\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n";

// 5. 測試熱力圖
echo "5. 測試熱力圖:\n";
$result = $pythonService->generateHeatmap($testData);
echo "   結果: " . ($result['success'] ? '✅' : '❌') . "\n";
if ($result['success']) {
    echo "   熱力圖點數: " . count($result['data']['heatmap_points']) . "\n";
} else {
    echo "   錯誤: " . $result['error'] . "\n";
}

echo "\n🎉 Python 整合測試完成！\n";
