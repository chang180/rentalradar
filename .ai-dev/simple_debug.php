<?php

/**
 * 簡單的資料關聯性檢查
 */

echo "檢查臺北市資料關聯性...\n\n";

// 直接讀取檔案內容
$mainFile = '.ai-dev/rental_data/a_lvr_land_c.csv';
$buildFile = '.ai-dev/rental_data/a_lvr_land_c_build.csv';

// 讀取主表編號
$mainIds = [];
$handle = fopen($mainFile, 'r');
$header = fgetcsv($handle, 0, ',', '"', '\\');
$lineCount = 0;

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    $lineCount++;
    
    // 跳過英文標題行
    if ($lineCount == 1 && isset($row[0]) && strpos($row[0], 'The ') === 0) {
        continue;
    }
    
    if (count($row) >= count($header)) {
        $data = array_combine($header, $row);
        if (!empty($data['編號'])) {
            $mainIds[] = $data['編號'];
        }
    }
}
fclose($handle);

// 讀取建物表編號
$buildIds = [];
$handle = fopen($buildFile, 'r');
$header = fgetcsv($handle, 0, ',', '"', '\\');
$lineCount = 0;

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    $lineCount++;
    
    // 跳過英文標題行
    if ($lineCount == 1 && isset($row[0]) && strpos($row[0], 'The ') === 0) {
        continue;
    }
    
    if (count($row) >= count($header)) {
        $data = array_combine($header, $row);
        // 處理BOM字符問題
        $idKey = '編號';
        if (!isset($data[$idKey])) {
            // 嘗試其他可能的鍵名
            foreach ($data as $key => $value) {
                if (strpos($key, '編號') !== false) {
                    $idKey = $key;
                    break;
                }
            }
        }
        if (!empty($data[$idKey])) {
            $buildIds[] = $data[$idKey];
        }
    }
}
fclose($handle);

echo "主表編號數量: " . count($mainIds) . "\n";
echo "建物表編號數量: " . count($buildIds) . "\n\n";

echo "主表前5個編號:\n";
for ($i = 0; $i < min(5, count($mainIds)); $i++) {
    echo "  $i: {$mainIds[$i]}\n";
}

echo "\n建物表前5個編號:\n";
for ($i = 0; $i < min(5, count($buildIds)); $i++) {
    echo "  $i: {$buildIds[$i]}\n";
}

// 檢查關聯性
$matched = array_intersect($mainIds, $buildIds);
echo "\n匹配的編號數量: " . count($matched) . "\n";

if (count($matched) > 0) {
    echo "匹配的編號範例:\n";
    $matchedArray = array_values($matched);
    for ($i = 0; $i < min(5, count($matchedArray)); $i++) {
        echo "  $i: {$matchedArray[$i]}\n";
    }
}

// 檢查重複
$mainUnique = array_unique($mainIds);
$buildUnique = array_unique($buildIds);

echo "\n主表唯一編號數: " . count($mainUnique) . "\n";
echo "建物表唯一編號數: " . count($buildUnique) . "\n";
echo "主表重複編號數: " . (count($mainIds) - count($mainUnique)) . "\n";
echo "建物表重複編號數: " . (count($buildIds) - count($buildUnique)) . "\n";
