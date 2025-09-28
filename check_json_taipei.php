<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "檢查 JSON 檔案中的臺北市內容:\n\n";

// 直接讀取 JSON 檔案
$jsonPath = __DIR__ . '/storage/app/taiwan_geo_centers.json';
$jsonContent = file_get_contents($jsonPath);
$data = json_decode($jsonContent, true);

echo "JSON 解析狀態: " . (json_last_error() === JSON_ERROR_NONE ? "✅ 成功" : "❌ 失敗: " . json_last_error_msg()) . "\n";

if (isset($data['geo_centers'])) {
    echo "geo_centers 存在: ✅\n";
    
    // 檢查臺北市
    if (isset($data['geo_centers']['臺北市'])) {
        echo "臺北市存在: ✅\n";
        echo "臺北市類型: " . gettype($data['geo_centers']['臺北市']) . "\n";
        echo "臺北市內容:\n";
        print_r($data['geo_centers']['臺北市']);
    } else {
        echo "臺北市不存在: ❌\n";
        
        // 檢查所有縣市
        echo "\n所有縣市列表:\n";
        foreach (array_keys($data['geo_centers']) as $city) {
            echo "- {$city}\n";
        }
    }
} else {
    echo "geo_centers 不存在: ❌\n";
}

// 檢查是否有其他台北相關的鍵
echo "\n檢查台北相關的鍵:\n";
if (isset($data['geo_centers'])) {
    foreach (array_keys($data['geo_centers']) as $city) {
        if (strpos($city, '北') !== false) {
            echo "- {$city}\n";
        }
    }
}
