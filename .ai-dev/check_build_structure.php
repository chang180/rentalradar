<?php

/**
 * 檢查建物表結構
 */

$buildFile = '.ai-dev/rental_data/a_lvr_land_c_build.csv';

echo "檢查建物表結構...\n\n";

$handle = fopen($buildFile, 'r');

// 讀取標題行
$header = fgetcsv($handle, 0, ',', '"', '\\');
echo "標題行:\n";
foreach ($header as $i => $col) {
    echo "  $i: $col\n";
}

echo "\n前5行資料:\n";
$lineCount = 0;
while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false && $lineCount < 5) {
    $lineCount++;
    echo "第 $lineCount 行:\n";
    foreach ($row as $i => $cell) {
        echo "  $i: $cell\n";
    }
    echo "\n";
}

fclose($handle);
