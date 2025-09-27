<?php

/**
 * 分析關聯性缺失的資料，了解特殊案件類型
 */

echo "分析關聯性缺失的資料...\n\n";

// 檢查臺北市資料
$mainFile = '.ai-dev/rental_data/a_lvr_land_c.csv';
$buildFile = '.ai-dev/rental_data/a_lvr_land_c_build.csv';

// 讀取主表資料
$mainData = [];
$handle = fopen($mainFile, 'r');
$header = fgetcsv($handle, 0, ',', '"', '\\');
$header = array_map(function($col) {
    return trim($col, "\xEF\xBB\xBF");
}, $header);

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    if (isset($row[0]) && strpos($row[0], 'The ') === 0) {
        continue;
    }
    
    if (count($row) >= count($header)) {
        $mainData[] = array_combine($header, $row);
    }
}
fclose($handle);

// 讀取建物表資料
$buildData = [];
$handle = fopen($buildFile, 'r');
$header = fgetcsv($handle, 0, ',', '"', '\\');
$header = array_map(function($col) {
    return trim($col, "\xEF\xBB\xBF");
}, $header);

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    if (isset($row[0]) && strpos($row[0], 'The ') === 0) {
        continue;
    }
    
    if (count($row) >= count($header)) {
        $buildData[] = array_combine($header, $row);
    }
}
fclose($handle);

// 建立建物表編號索引
$buildIds = array_column($buildData, '編號');
$buildIndex = array_flip($buildIds);

echo "臺北市資料分析:\n";
echo "主表總數: " . count($mainData) . "\n";
echo "建物表總數: " . count($buildData) . "\n";

// 分析有建物資訊的案件
$withBuilding = 0;
$withoutBuilding = 0;
$specialCases = [];

foreach ($mainData as $index => $record) {
    $id = $record['編號'];
    if (isset($buildIndex[$id])) {
        $withBuilding++;
    } else {
        $withoutBuilding++;
        $specialCases[] = [
            'index' => $index,
            'id' => $id,
            'district' => $record['鄉鎮市區'],
            'type' => $record['交易標的'],
            'rent' => $record['總額元'],
            'area' => $record['建物總面積平方公尺'],
            'note' => $record['備註']
        ];
    }
}

echo "有建物資訊的案件: $withBuilding\n";
echo "無建物資訊的案件: $withoutBuilding\n";
echo "關聯率: " . round(($withBuilding / count($mainData)) * 100, 2) . "%\n\n";

echo "特殊案件分析 (前10筆):\n";
foreach (array_slice($specialCases, 0, 10) as $case) {
    echo "編號: {$case['id']}\n";
    echo "行政區: {$case['district']}\n";
    echo "交易標的: {$case['type']}\n";
    echo "租金: {$case['rent']}\n";
    echo "面積: {$case['area']}\n";
    echo "備註: {$case['note']}\n";
    echo "---\n";
}

// 分析特殊案件類型
$typeAnalysis = [];
foreach ($specialCases as $case) {
    $type = $case['type'];
    if (!isset($typeAnalysis[$type])) {
        $typeAnalysis[$type] = 0;
    }
    $typeAnalysis[$type]++;
}

echo "\n特殊案件類型分析:\n";
arsort($typeAnalysis);
foreach ($typeAnalysis as $type => $count) {
    echo "$type: $count 筆\n";
}

// 分析建物表重複案件
$buildIdCounts = array_count_values($buildIds);
$duplicateBuildIds = array_filter($buildIdCounts, function($count) {
    return $count > 1;
});

echo "\n建物表重複案件分析:\n";
echo "重複編號數量: " . count($duplicateBuildIds) . "\n";
echo "重複案件總數: " . array_sum($duplicateBuildIds) . "\n";

echo "\n重複案件範例 (前5個):\n";
$duplicateExamples = array_slice($duplicateBuildIds, 0, 5, true);
foreach ($duplicateExamples as $id => $count) {
    echo "編號 $id: $count 筆建物記錄\n";
}
