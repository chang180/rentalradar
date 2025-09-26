# AI資料處理策略

## 📊 政府資料結構分析

### 資料檔案結構
基於 `rental_data` 目錄的實際資料分析：

**主要資料檔案：**
- **縣市資料**：`a_lvr_land_c.csv` 到 `x_lvr_land_c.csv` (各縣市租賃資料)
- **建物資料**：`*_build.csv` (建物詳細資訊)
- **土地資料**：`*_land.csv` (土地資訊)
- **停車位資料**：`*_park.csv` (停車位資訊)

### 資料欄位分析
**主要租賃資料 (36個欄位)：**
- 基本資訊：鄉鎮市區、交易標的、土地位置建物門牌
- 價格資訊：總額元、單價元平方公尺、車位總額元
- 建物資訊：建物總面積、格局(房廳衛)、樓層、建物型態
- 設施資訊：有無電梯、管理組織、附傢俱、附屬設備

**建物詳細資料 (10個欄位)：**
- 編號、屋齡、建物移轉面積、主要用途、主要建材
- 建築完成日期、總層數、建物分層、移轉情形

## 🤖 AI資料處理策略

### 1. AI地址分析與簡化
```php
// AI地址拆分策略
class AIAddressAnalyzer
{
    public function aiAnalyzeAddress($fullAddress)
    {
        // 範例：臺北市萬華區艋舺大道３８６巷３號
        return [
            'county' => '臺北市',                    // AI提取縣市
            'district' => '萬華區',                  // AI提取行政區
            'street' => '艋舺大道３８６巷',           // AI提取街道
            'house_number' => '３號',                // AI提取門牌
            'simplified_address' => '萬華區艋舺大道', // AI簡化地址
            'geocoded_lat' => null,                  // AI地理編碼緯度
            'geocoded_lng' => null,                  // AI地理編碼經度
            'confidence_score' => 95                 // AI信心度評分
        ];
    }
    
    public function aiSimplifyAddress($fullAddress)
    {
        // AI智慧簡化：保留重要資訊，去除詳細門牌
        $patterns = [
            '/\d+號.*$/' => '',           // 移除門牌號碼
            '/\d+樓.*$/' => '',           // 移除樓層資訊
            '/\d+巷.*$/' => '',           // 移除巷弄資訊
            '/\d+弄.*$/' => '',           // 移除弄資訊
        ];
        
        $simplified = $fullAddress;
        foreach ($patterns as $pattern => $replacement) {
            $simplified = preg_replace($pattern, $replacement, $simplified);
        }
        
        return trim($simplified);
    }
}
```

### 2. AI逐檔處理策略
```php
// AI記憶體優化處理
class AIMemoryOptimizedProcessor
{
    public function aiProcessFilesInBatches($files)
    {
        $results = [];
        
        foreach ($files as $file) {
            // 逐檔處理，避免記憶體問題
            $data = $this->aiProcessSingleFile($file);
            $results = array_merge($results, $data);
            
            // AI記憶體清理
            $this->aiCleanupMemory();
            
            // AI進度追蹤
            $this->aiTrackProgress($file, count($results));
        }
        
        return $results;
    }
    
    private function aiProcessSingleFile($file)
    {
        // AI智慧CSV解析
        $data = $this->aiParseCSV($file);
        
        // AI資料清理
        $cleanedData = $this->aiCleanData($data);
        
        // AI異常值檢測
        $validatedData = $this->aiDetectAnomalies($cleanedData);
        
        return $validatedData;
    }
    
    private function aiCleanupMemory()
    {
        // AI記憶體優化
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // AI記憶體監控
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage > 128 * 1024 * 1024) { // 128MB
            $this->aiLogMemoryWarning($memoryUsage);
        }
    }
}
```

### 3. AI資料品質控制
```php
// AI異常值檢測
class AIAnomalyDetector
{
    public function aiDetectAnomalies($data)
    {
        $anomalies = [];
        
        foreach ($data as $record) {
            // AI價格合理性檢測
            if ($this->aiIsPriceAnomaly($record)) {
                $record['ai_anomaly_detected'] = true;
                $record['ai_confidence_score'] -= 20;
            }
            
            // AI坪數邏輯性檢測
            if ($this->aiIsAreaAnomaly($record)) {
                $record['ai_anomaly_detected'] = true;
                $record['ai_confidence_score'] -= 15;
            }
            
            // AI地理一致性檢測
            if ($this->aiIsLocationAnomaly($record)) {
                $record['ai_anomaly_detected'] = true;
                $record['ai_confidence_score'] -= 10;
            }
            
            $anomalies[] = $record;
        }
        
        return $anomalies;
    }
    
    private function aiIsPriceAnomaly($record)
    {
        $price = $record['total_amount'] ?? 0;
        $area = $record['building_area'] ?? 1;
        $unitPrice = $price / $area;
        
        // AI價格區間檢測 (3,000-150,000元)
        if ($price < 3000 || $price > 150000) {
            return true;
        }
        
        // AI單價合理性檢測 (500-2000元/坪)
        if ($unitPrice < 500 || $unitPrice > 2000) {
            return true;
        }
        
        return false;
    }
}
```

### 4. AI地理編碼策略
```php
// AI地理編碼系統
class AIGeocodingSystem
{
    public function aiGeocodeAddress($address)
    {
        // AI地址標準化
        $standardizedAddress = $this->aiStandardizeAddress($address);
        
        // AI多來源地理編碼
        $geocodingResults = [
            'google' => $this->aiGoogleGeocoding($standardizedAddress),
            'opencage' => $this->aiOpenCageGeocoding($standardizedAddress),
            'nominatim' => $this->aiNominatimGeocoding($standardizedAddress)
        ];
        
        // AI結果融合與驗證
        return $this->aiFuseGeocodingResults($geocodingResults);
    }
    
    private function aiFuseGeocodingResults($results)
    {
        // AI智慧結果融合
        $validResults = array_filter($results, function($result) {
            return $result && $result['confidence'] > 0.7;
        });
        
        if (empty($validResults)) {
            return null;
        }
        
        // AI加權平均
        $totalWeight = 0;
        $latSum = 0;
        $lngSum = 0;
        
        foreach ($validResults as $result) {
            $weight = $result['confidence'];
            $latSum += $result['lat'] * $weight;
            $lngSum += $result['lng'] * $weight;
            $totalWeight += $weight;
        }
        
        return [
            'lat' => $latSum / $totalWeight,
            'lng' => $lngSum / $totalWeight,
            'confidence' => $totalWeight / count($validResults)
        ];
    }
}
```

## 🚀 部署策略

### 本機開發環境
- **資料庫**：SQLite (快速開發)
- **處理方式**：逐檔處理，避免記憶體問題
- **測試策略**：小量資料測試，驗證AI演算法

### 正式部署環境
- **資料庫**：MySQL (生產環境)
- **處理方式**：批次處理，定時更新
- **監控策略**：AI效能監控，自動優化

### 記憶體優化策略
- **逐檔處理**：避免一次性載入大量資料
- **記憶體清理**：處理完畢後立即清理
- **批次大小**：根據記憶體限制調整批次大小
- **AI監控**：即時監控記憶體使用情況

---

**🎯 下一步行動：**
1. 建立Herd專案
2. 實作AI地址分析器
3. 開發AI逐檔處理器
4. 測試AI異常值檢測
5. 整合AI地理編碼系統
