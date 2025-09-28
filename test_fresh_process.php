<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "全新進程測試 getGeoCenter:\n\n";

// 不重置任何快取，直接測試
$center = \App\Services\TaiwanGeoCenterService::getGeoCenter('臺北市', '中山區');
echo "臺北市 中山區: " . ($center ? "✅ 有座標" : "❌ 無座標") . "\n";

if ($center) {
    echo "座標: {$center['lat']}, {$center['lng']}\n";
}

// 測試其他城市
echo "\n測試其他城市:\n";
$testCases = [
    ['city' => '台中市', 'district' => '西屯區'],
    ['city' => '台南市', 'district' => '永康區'],
    ['city' => '臺北市', 'district' => '中正區'],
];

foreach ($testCases as $test) {
    $center = \App\Services\TaiwanGeoCenterService::getGeoCenter($test['city'], $test['district']);
    echo "- {$test['city']} {$test['district']}: " . ($center ? "✅" : "❌") . "\n";
}
