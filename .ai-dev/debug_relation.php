<?php

/**
 * 除錯資料關聯性
 */

// 檢查臺北市資料的關聯性
$mainFile = '.ai-dev/rental_data/a_lvr_land_c.csv';
$buildFile = '.ai-dev/rental_data/a_lvr_land_c_build.csv';

echo "檢查臺北市資料關聯性...\n\n";

// 讀取主表資料
$mainData = [];
$handle = fopen($mainFile, 'r');
$header = fgetcsv($handle, 0, ',', '"', '\\');

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    // 跳過英文標題行
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

$lineCount = 0;
while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    $lineCount++;
    
    // 跳過英文標題行（第二行）
    if ($lineCount == 1 && isset($row[0]) && strpos($row[0], 'The ') === 0) {
        continue;
    }
    
    if (count($row) >= count($header)) {
        $buildData[] = array_combine($header, $row);
    }
}
fclose($handle);

echo "主表資料筆數: " . count($mainData) . "\n";
echo "建物表資料筆數: " . count($buildData) . "\n\n";

// 檢查前5筆主表資料的編號
echo "主表前5筆編號:\n";
for ($i = 0; $i < min(5, count($mainData)); $i++) {
    $id = $mainData[$i]['編號'] ?? 'N/A';
    echo "  $i: $id\n";
}

echo "\n建物表前5筆編號:\n";
for ($i = 0; $i < min(5, count($buildData)); $i++) {
    $id = $buildData[$i]['編號'] ?? 'N/A';
    echo "  $i: $id\n";
}

// 檢查關聯性
$mainIds = array_column($mainData, '編號');
$buildIds = array_column($buildData, '編號');

echo "\n關聯性檢查:\n";
echo "主表唯一編號數: " . count(array_unique($mainIds)) . "\n";
echo "建物表唯一編號數: " . count(array_unique($buildIds)) . "\n";

$matched = array_intersect($mainIds, $buildIds);
echo "匹配的編號數: " . count($matched) . "\n";

if (count($matched) > 0) {
    echo "匹配的編號範例:\n";
    $matchedArray = array_values($matched);
    for ($i = 0; $i < min(5, count($matchedArray)); $i++) {
        echo "  $i: {$matchedArray[$i]}\n";
    }
}

// 檢查是否有重複的編號
$mainDuplicates = array_diff_assoc($mainIds, array_unique($mainIds));
$buildDuplicates = array_diff_assoc($buildIds, array_unique($buildIds));

if (!empty($mainDuplicates)) {
    echo "\n主表重複編號:\n";
    foreach (array_unique($mainDuplicates) as $dup) {
        echo "  $dup\n";
    }
}

if (!empty($buildDuplicates)) {
    echo "\n建物表重複編號:\n";
    foreach (array_unique($buildDuplicates) as $dup) {
        echo "  $dup\n";
    }
}
