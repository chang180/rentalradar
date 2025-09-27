<?php

/**
 * 租屋資料解析分析腳本
 * 用於分析 rental_data 目錄中的資料結構和內容
 */

class RentalDataAnalyzer
{
    private $dataPath;
    private $manifest;
    private $buildTime;
    private $cityMapping = [];
    private $analysisResults = [];

    public function __construct($dataPath = '.ai-dev/rental_data')
    {
        $this->dataPath = $dataPath;
    }

    /**
     * 解析 manifest.csv 建立縣市對應表
     */
    public function parseManifest()
    {
        $manifestFile = $this->dataPath . '/manifest.csv';
        if (!file_exists($manifestFile)) {
            throw new Exception("Manifest file not found: $manifestFile");
        }

        $this->manifest = [];
        $handle = fopen($manifestFile, 'r');
        $header = fgetcsv($handle); // 跳過標題行

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $name = $row[0];
            $description = $row[2];
            
            // 解析縣市名稱
            if (preg_match('/(.+?)(市|縣)/', $description, $matches)) {
                $city = $matches[1] . $matches[2];
                $prefix = substr($name, 0, 1);
                $this->cityMapping[$prefix] = $city;
            }
        }
        fclose($handle);

        echo "縣市對應表建立完成:\n";
        foreach ($this->cityMapping as $prefix => $city) {
            echo "  $prefix => $city\n";
        }
        echo "\n";
    }

    /**
     * 解析 build_time.xml 獲取時間範圍
     */
    public function parseBuildTime()
    {
        $buildTimeFile = $this->dataPath . '/build_time.xml';
        if (!file_exists($buildTimeFile)) {
            throw new Exception("Build time file not found: $buildTimeFile");
        }

        $xml = simplexml_load_file($buildTimeFile);
        $timeText = (string)$xml->lvr_time;
        
        // 解析租賃案件時間範圍
        if (preg_match('/訂約日期\s*(\d+)年(\d+)月(\d+)日至\s*(\d+)年(\d+)月(\d+)日/', $timeText, $matches)) {
            $this->buildTime = [
                'start_year' => (int)$matches[1],
                'start_month' => (int)$matches[2],
                'start_day' => (int)$matches[3],
                'end_year' => (int)$matches[4],
                'end_month' => (int)$matches[5],
                'end_day' => (int)$matches[6],
            ];
        }

        echo "時間範圍解析:\n";
        echo "  租賃案件: {$this->buildTime['start_year']}年{$this->buildTime['start_month']}月{$this->buildTime['start_day']}日 至 {$this->buildTime['end_year']}年{$this->buildTime['end_month']}月{$this->buildTime['end_day']}日\n";
        echo "\n";
    }

    /**
     * 分析特定縣市的資料
     */
    public function analyzeCityData($prefix)
    {
        $city = $this->cityMapping[$prefix] ?? '未知縣市';
        echo "分析 $city ($prefix) 的資料...\n";

        $mainRentFile = $this->dataPath . "/{$prefix}_lvr_land_c.csv";
        $buildFile = $this->dataPath . "/{$prefix}_lvr_land_c_build.csv";

        $results = [
            'city' => $city,
            'prefix' => $prefix,
            'main_rent_count' => 0,
            'build_count' => 0,
            'matched_count' => 0,
            'sample_data' => [],
            'issues' => []
        ];

        // 分析主表資料
        if (file_exists($mainRentFile)) {
            $mainData = $this->parseCsvFile($mainRentFile);
            $results['main_rent_count'] = count($mainData);
            
            // 分析前5筆資料
            $results['sample_data'] = array_slice($mainData, 0, 5);
            
            // 檢查資料品質
            $this->checkDataQuality($mainData, $results);
        }

        // 分析建物表資料
        if (file_exists($buildFile)) {
            $buildData = $this->parseCsvFile($buildFile);
            $results['build_count'] = count($buildData);
        }

        // 檢查關聯性
        if (file_exists($mainRentFile) && file_exists($buildFile)) {
            $results['matched_count'] = $this->checkDataRelation($mainData, $buildData);
        }

        $this->analysisResults[$prefix] = $results;
        return $results;
    }

    /**
     * 解析 CSV 檔案
     */
    private function parseCsvFile($filePath)
    {
        $data = [];
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        
        // 處理BOM字符問題
        $header = array_map(function($col) {
            return trim($col, "\xEF\xBB\xBF");
        }, $header);
        
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            // 跳過英文標題行（第二行）
            if (isset($row[0]) && strpos($row[0], 'The ') === 0) {
                continue;
            }
            
            // 檢查是否為有效資料行
            if (count($row) >= count($header)) {
                $data[] = array_combine($header, $row);
            }
        }
        fclose($handle);
        
        return $data;
    }

    /**
     * 檢查資料品質
     */
    private function checkDataQuality($data, &$results)
    {
        $issues = [];
        
        foreach ($data as $index => $row) {
            // 檢查必要欄位
            if (empty($row['編號'])) {
                $issues[] = "第 $index 筆資料缺少編號";
            }
            
            if (empty($row['鄉鎮市區'])) {
                $issues[] = "第 $index 筆資料缺少鄉鎮市區";
            }
            
            // 檢查時間格式
            if (!empty($row['租賃年月日']) && !preg_match('/^\d{7}$/', $row['租賃年月日'])) {
                $issues[] = "第 $index 筆資料時間格式錯誤: {$row['租賃年月日']}";
            }
            
            // 檢查租金資料
            if (!empty($row['總額元']) && !is_numeric($row['總額元'])) {
                $issues[] = "第 $index 筆資料租金格式錯誤: {$row['總額元']}";
            }
        }
        
        $results['issues'] = array_slice($issues, 0, 10); // 只顯示前10個問題
    }

    /**
     * 檢查資料關聯性
     */
    private function checkDataRelation($mainData, $buildData)
    {
        $mainIds = array_column($mainData, '編號');
        $buildIds = array_column($buildData, '編號');
        
        $matched = array_intersect($mainIds, $buildIds);
        return count($matched);
    }

    /**
     * 測試時間轉換函數
     */
    public function testTimeConversion()
    {
        echo "測試時間轉換函數:\n";
        
        $testDates = ['1140801', '1140805', '1140810'];
        
        foreach ($testDates as $date) {
            $converted = $this->convertTaiwanDate($date);
            echo "  $date => $converted\n";
        }
        echo "\n";
    }

    /**
     * 測試面積轉換函數
     */
    public function testAreaConversion()
    {
        echo "測試面積轉換函數:\n";
        
        $testAreas = [62.2, 88.99, 810.95];
        
        foreach ($testAreas as $area) {
            $ping = $this->convertToPing($area);
            echo "  {$area} 平方公尺 => " . round($ping, 2) . " 坪\n";
        }
        echo "\n";
    }

    /**
     * 轉換民國年日期
     */
    private function convertTaiwanDate($date)
    {
        if (strlen($date) !== 7) return null;
        
        $year = (int)substr($date, 0, 3);
        $month = (int)substr($date, 3, 2);
        $day = (int)substr($date, 5, 2);
        
        $westernYear = $year + 1911;
        return "$westernYear-$month-$day";
    }

    /**
     * 轉換平方公尺為坪數
     */
    private function convertToPing($squareMeters)
    {
        return $squareMeters / 3.30579;
    }

    /**
     * 生成分析報告
     */
    public function generateReport()
    {
        echo "=== 租屋資料分析報告 ===\n\n";
        
        echo "1. 縣市對應表:\n";
        foreach ($this->cityMapping as $prefix => $city) {
            echo "   $prefix => $city\n";
        }
        echo "\n";
        
        echo "2. 時間範圍:\n";
        echo "   租賃案件: {$this->buildTime['start_year']}年{$this->buildTime['start_month']}月{$this->buildTime['start_day']}日 至 {$this->buildTime['end_year']}年{$this->buildTime['end_month']}月{$this->buildTime['end_day']}日\n";
        echo "\n";
        
        echo "3. 各縣市資料統計:\n";
        foreach ($this->analysisResults as $prefix => $result) {
            echo "   {$result['city']} ($prefix):\n";
            echo "     主表資料: {$result['main_rent_count']} 筆\n";
            echo "     建物表資料: {$result['build_count']} 筆\n";
            echo "     關聯匹配: {$result['matched_count']} 筆\n";
            
            if (!empty($result['issues'])) {
                echo "     發現問題:\n";
                foreach ($result['issues'] as $issue) {
                    echo "       - $issue\n";
                }
            }
            echo "\n";
        }
        
        echo "4. 建議處理方案:\n";
        echo "   - 建立縣市對應表: 完成\n";
        echo "   - 時間格式轉換: 民國年轉西元年\n";
        echo "   - 面積單位轉換: 平方公尺轉坪數\n";
        echo "   - 資料關聯性: 透過編號欄位關聯\n";
        echo "   - 租金重新計算: 總額 ÷ 坪數 = 每坪租金\n";
    }
}

// 執行分析
try {
    $analyzer = new RentalDataAnalyzer();
    
    echo "開始分析租屋資料...\n\n";
    
    // 1. 解析 manifest
    $analyzer->parseManifest();
    
    // 2. 解析時間範圍
    $analyzer->parseBuildTime();
    
    // 3. 測試轉換函數
    $analyzer->testTimeConversion();
    $analyzer->testAreaConversion();
    
    // 4. 分析主要縣市資料
    $mainCities = ['a', 'b', 'c', 'd', 'e', 'f']; // 台北、台中、基隆、台南、高雄、新北
    
    foreach ($mainCities as $prefix) {
        $analyzer->analyzeCityData($prefix);
    }
    
    // 5. 生成報告
    $analyzer->generateReport();
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
