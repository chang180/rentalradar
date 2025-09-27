# DEV-24: 真實政府資料下載與地圖顯示整合測試

## 📋 任務概述

測試完整的政府資料下載、解析、儲存到資料庫，並驗證資料能正確顯示在地圖上的完整流程。

## 🎯 主要目標

1. **下載真實的政府租賃實價登錄資料**
2. **解析並儲存到資料庫**
3. **執行地理編碼處理**
4. **驗證資料在地圖上的顯示效果**
5. **測試 AI 聚合演算法的實際效果**

## 📊 預期成果

- ✅ 成功下載並處理 100+ 筆真實租賃資料
- ✅ 驗證地圖上能正確顯示租賃物件
- ✅ 測試 AI 聚合功能在真實資料上的表現
- ✅ 確認地理編碼準確性
- ✅ 驗證效能監控系統運作正常

## 🔧 技術要求

- 使用現有的 `GovernmentDataDownloadService`
- 整合 `DataParserService` 和 `GeocodingService`
- 測試 `MapController` API 端點
- 驗證前端地圖組件顯示
- 確保所有測試通過

## 📈 成功指標

- 資料下載成功率 > 95%
- 地理編碼成功率 > 80%
- 地圖載入時間 < 3秒
- AI 聚合演算法正常運作
- 所有相關測試通過

## 🚀 執行步驟

### 步驟 1: 資料下載測試

```bash
# 下載 CSV 格式資料
php artisan government:download --format=csv --parse --save

# 檢查下載狀態
php artisan government:maintenance --status

# 查看下載的檔案
ls storage/app/government-data/
```

**預期結果**:
- 成功下載 CSV 檔案
- 檔案大小通常在 500KB - 2MB
- 下載時間 < 30秒

### 步驟 2: 資料解析與儲存

```bash
# 檢查解析結果
php artisan tinker
>>> Property::count()
>>> Property::latest()->first()

# 查看資料品質
>>> Property::where('is_processed', true)->count()
>>> Property::where('is_geocoded', true)->count()
```

**預期結果**:
- 資料庫中有新的 Property 記錄
- 解析成功率 > 85%
- 資料格式正確

### 步驟 3: 地理編碼處理

```bash
# 執行地理編碼
php artisan properties:geocode --limit=50

# 檢查地理編碼結果
php artisan tinker
>>> Property::geocoded()->count()
>>> Property::whereNotNull('latitude')->count()
```

**預期結果**:
- 地理編碼成功率 > 80%
- 座標資料正確
- 地址格式優化

### 步驟 4: 地圖 API 測試

```bash
# 測試基本物件 API
curl "https://rentalradar.test/api/map/properties" | jq

# 測試 AI 聚合 API
curl "https://rentalradar.test/api/map/clusters" | jq

# 測試優化資料 API
curl "https://rentalradar.test/api/map/optimized-data" | jq
```

**預期結果**:
- API 回應正常 (200 狀態碼)
- 資料結構正確
- 包含效能指標
- AI 聚合演算法正常運作

### 步驟 5: 前端地圖驗證

1. **訪問地圖頁面**: `https://rentalradar.test/map`
2. **檢查資料顯示**:
   - 租賃物件標記正確顯示
   - 地理編碼位置準確
   - 物件資訊完整
3. **測試 AI 功能**:
   - 切換 AI 聚合模式
   - 測試熱力圖顯示
   - 驗證價格預測功能
4. **檢查效能監控**:
   - 訪問效能監控儀表板
   - 確認監控數據正常

**預期結果**:
- 地圖載入時間 < 3秒
- 所有物件正確顯示
- AI 功能正常運作
- 效能監控數據完整

## 🔍 測試檢查清單

### 資料下載階段
- [ ] 政府資料下載成功
- [ ] CSV 檔案格式正確
- [ ] 下載時間在合理範圍內
- [ ] 檔案大小符合預期

### 資料處理階段
- [ ] CSV 解析無錯誤
- [ ] 資料庫儲存完整
- [ ] 資料驗證通過
- [ ] 錯誤處理正常

### 地理編碼階段
- [ ] 地理編碼處理完成
- [ ] 座標資料準確
- [ ] 地址格式優化
- [ ] 重試機制正常

### API 測試階段
- [ ] 地圖 API 回應正常
- [ ] 資料結構正確
- [ ] 效能指標完整
- [ ] 錯誤處理適當

### 前端驗證階段
- [ ] 地圖載入正常
- [ ] 物件顯示正確
- [ ] AI 功能運作
- [ ] 效能監控正常

## 🛠️ 故障排除

### 常見問題與解決方案

#### 1. 下載失敗
**症狀**: 政府資料下載失敗
**解決方案**:
```bash
# 檢查網路連接
curl -I https://data.moi.gov.tw/MoiOD/System/DownloadFile.aspx

# 檢查系統狀態
php artisan government:maintenance --status

# 重新下載
php artisan government:download --format=csv --parse --save
```

#### 2. 解析錯誤
**症狀**: CSV 解析失敗或資料不完整
**解決方案**:
```bash
# 檢查檔案格式
php artisan government:test --full

# 查看詳細錯誤
tail -f storage/logs/laravel.log

# 手動解析測試
php artisan tinker
>>> $service = app(\App\Services\DataParserService::class);
>>> $result = $service->parseCsvData('storage/app/government-data/最新檔案.csv');
```

#### 3. 地理編碼失敗
**症狀**: 地理編碼成功率低或 API 限制
**解決方案**:
```bash
# 檢查 OpenStreetMap API
curl "https://nominatim.openstreetmap.org/search?q=台北市&format=json&limit=1"

# 執行地理編碼
php artisan properties:geocode --limit=10

# 檢查地理編碼結果
php artisan tinker
>>> Property::where('is_geocoded', false)->count()
```

#### 4. 地圖顯示問題
**症狀**: 地圖上物件位置不正確或無法顯示
**解決方案**:
```bash
# 檢查 API 端點
curl "https://rentalradar.test/api/map/properties"

# 檢查資料庫資料
php artisan tinker
>>> Property::geocoded()->whereNotNull('latitude')->count()

# 檢查前端控制台錯誤
# 開啟瀏覽器開發者工具查看 Console 錯誤
```

#### 5. AI 聚合問題
**症狀**: AI 聚合演算法無法正常運作
**解決方案**:
```bash
# 測試 AI 聚合 API
curl "https://rentalradar.test/api/map/clusters"

# 檢查效能監控
curl "https://rentalradar.test/api/map/optimized-data"

# 查看 AI 服務狀態
php artisan tinker
>>> $service = app(\App\Services\AIMapOptimizationService::class);
>>> $result = $service->generateClusters(Property::geocoded()->limit(100)->get());
```

## 📊 效能監控

### 關鍵指標

1. **下載效能**
   - 下載速度: < 30秒
   - 檔案大小: 500KB - 2MB
   - 成功率: > 95%

2. **解析效能**
   - 處理速度: < 60秒 (1000筆資料)
   - 記憶體使用: < 100MB
   - 錯誤率: < 5%

3. **地理編碼效能**
   - 處理速度: < 10秒 (100筆資料)
   - 成功率: > 80%
   - API 限制: 1 request/second

4. **地圖效能**
   - 載入時間: < 3秒
   - API 響應: < 500ms
   - 記憶體使用: < 100MB

### 監控工具

```bash
# 查看系統日誌
tail -f storage/logs/laravel.log

# 檢查效能監控儀表板
# 訪問: https://rentalradar.test/performance

# 查看資料庫統計
php artisan tinker
>>> Property::count()
>>> Property::geocoded()->count()
>>> Property::where('is_processed', true)->count()
```

## 🎯 測試其他 AI 的準備

### 給 Claude Code 的指示

**任務**: 執行真實資料下載與地圖顯示整合測試

**重點**:
1. 使用現有的政府資料下載系統
2. 測試完整資料流程
3. 驗證地圖顯示效果
4. 確保所有測試通過
5. 隨時更新PROGRESS.md檔案，以便掌握總體進度

**關鍵檔案**:
- `app/Services/GovernmentDataDownloadService.php`
- `app/Services/DataParserService.php`
- `app/Services/GeocodingService.php`
- `app/Http/Controllers/MapController.php`
- `resources/js/pages/map.tsx`

**測試命令**:
```bash
php artisan government:download --format=csv --parse --save
php artisan properties:geocode --limit=50
php artisan test tests/Feature/MapIntegrationTest.php
```

### 給 Codex 的指示

**任務**: 優化 AI 聚合演算法在真實資料上的表現

**重點**:
1. 分析真實資料特徵
2. 優化聚合演算法參數
3. 提升地理編碼準確性
4. 改善效能監控指標

**關鍵檔案**:
- `app/Services/AIMapOptimizationService.php`
- `resources/js/services/AIMapService.ts`
- `app/Support/PerformanceMonitor.php`

**測試命令**:
```bash
php artisan test tests/Feature/AdvancedPricePredictionTest.php
php artisan test tests/Feature/ModelConsistencyTest.php
```

## 📝 完成標準

### 必須完成
- [ ] 成功下載 100+ 筆真實租賃資料
- [ ] 地理編碼成功率 > 80%
- [ ] 地圖正確顯示所有物件
- [ ] AI 聚合功能正常運作
- [ ] 所有相關測試通過

### 建議完成
- [ ] 效能監控數據完整
- [ ] 錯誤處理機制完善
- [ ] 使用者體驗優化
- [ ] 文檔更新完整

## 🚀 後續步驟

完成此任務後，建議進行：

1. **效能優化**: 根據真實資料調整演算法參數
2. **使用者體驗**: 優化地圖互動和顯示效果
3. **資料品質**: 建立資料品質監控機制
4. **自動化**: 設定定期資料更新排程

---

**建立時間**: 2025-09-27  
**負責 AI**: Claude (架構師)  
**預估時間**: 4-6 小時  
**優先級**: High
