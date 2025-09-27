<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Services\AIModelTrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIModelTrainingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AIModelTrainingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AIModelTrainingService;

        // 創建測試資料
        Property::factory()->count(20)->create([
            'rent_per_month' => 25000,
            'total_floor_area' => 30,
            'district' => '中正區',
            'building_type' => '公寓',
            'compartment_pattern' => '2房1廳1衛',
        ]);
    }

    public function test_train_rent_prediction_model_returns_success(): void
    {
        $result = $this->service->trainRentPredictionModel();

        if ($result['status'] === 'error') {
            $this->fail('Training failed: '.($result['message'] ?? 'Unknown error'));
        }

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('accuracy', $result);
        $this->assertArrayHasKey('training_samples', $result);
        $this->assertArrayHasKey('model_version', $result);
        $this->assertArrayHasKey('last_trained', $result);
        $this->assertGreaterThanOrEqual(0, $result['accuracy']); // 允許 0 準確度（測試環境）
        $this->assertGreaterThan(0, $result['training_samples']);
    }

    public function test_model_can_be_saved_and_loaded(): void
    {
        // 先訓練模型
        $this->service->trainRentPredictionModel();

        // 檢查模型是否能被載入
        $model = $this->service->loadModel();

        $this->assertNotNull($model);
        $this->assertIsObject($model);
    }

    public function test_get_model_info_returns_valid_info(): void
    {
        // 訓練模型
        $result = $this->service->trainRentPredictionModel();

        if ($result['status'] === 'error') {
            $this->fail('Training failed: '.($result['message'] ?? 'Unknown error'));
        }

        $info = $this->service->getModelInfo();

        $this->assertArrayHasKey('status', $info);
        $this->assertArrayHasKey('last_trained', $info);
        $this->assertArrayHasKey('model_version', $info);
        $this->assertEquals('loaded', $info['status']);
    }

    public function test_prepare_training_data_returns_array(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('prepareTrainingData');
        $method->setAccessible(true);

        $data = $method->invoke($this->service);

        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
    }

    public function test_extract_features_returns_valid_features(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractFeatures');
        $method->setAccessible(true);

        $testData = [
            [
                'rent_per_month' => 25000,
                'total_floor_area' => 30,
                'district' => '中正區',
                'building_type' => '公寓',
                'compartment_pattern' => '2房1廳1衛',
                'rent_date' => '2023-01-01',
            ],
        ];

        $features = $method->invoke($this->service, $testData);

        $this->assertIsArray($features);
        $this->assertArrayHasKey('area', $features[0]);
        $this->assertArrayHasKey('rooms', $features[0]);
        $this->assertArrayHasKey('district_encoded', $features[0]);
        $this->assertArrayHasKey('building_type_encoded', $features[0]);
    }

    public function test_normalize_features_returns_normalized_values(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizeFeatures');
        $method->setAccessible(true);

        $features = [
            ['area' => 30, 'rooms' => 2, 'district_encoded' => 0.5, 'building_type_encoded' => 0.3, 'age' => 5, 'price_per_sqm' => 833, 'target' => 25000],
            ['area' => 60, 'rooms' => 3, 'district_encoded' => 0.7, 'building_type_encoded' => 0.5, 'age' => 3, 'price_per_sqm' => 667, 'target' => 40000],
            ['area' => 45, 'rooms' => 2, 'district_encoded' => 0.6, 'building_type_encoded' => 0.4, 'age' => 4, 'price_per_sqm' => 711, 'target' => 32000],
        ];

        $normalized = $method->invoke($this->service, $features);

        $this->assertIsArray($normalized);
        $this->assertCount(3, $normalized);

        // 檢查標準化後的值在合理範圍內
        foreach ($normalized as $feature) {
            $this->assertGreaterThanOrEqual(-3, $feature['area']);
            $this->assertLessThanOrEqual(3, $feature['area']);
            $this->assertGreaterThanOrEqual(-3, $feature['rooms']);
            $this->assertLessThanOrEqual(3, $feature['rooms']);
        }
    }

    public function test_calculate_linear_regression_returns_valid_coefficients(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateLinearRegression');
        $method->setAccessible(true);

        $features = [
            ['area' => 30, 'rooms' => 2, 'district_encoded' => 0.5, 'building_type_encoded' => 0.3, 'age' => 5, 'price_per_sqm' => 833, 'target' => 25000],
            ['area' => 60, 'rooms' => 3, 'district_encoded' => 0.7, 'building_type_encoded' => 0.5, 'age' => 3, 'price_per_sqm' => 667, 'target' => 40000],
            ['area' => 45, 'rooms' => 2, 'district_encoded' => 0.6, 'building_type_encoded' => 0.4, 'age' => 4, 'price_per_sqm' => 711, 'target' => 32000],
        ];

        $result = $method->invoke($this->service, $features);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('coefficients', $result);
        $this->assertArrayHasKey('intercept', $result);
        $this->assertArrayHasKey('r_squared', $result);
        $this->assertIsArray($result['coefficients']);
        $this->assertIsFloat($result['intercept']);
        $this->assertIsFloat($result['r_squared']);
    }

    public function test_validate_model_returns_accuracy_score(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateModel');
        $method->setAccessible(true);

        // 創建一個簡單的模型物件
        $model = (object) [
            'coefficients' => [100, 50],
            'intercept' => 1000,
            'feature_names' => ['area', 'rooms'],
        ];

        $features = [
            ['area' => 30, 'rooms' => 2, 'target' => 25000],
            ['area' => 60, 'rooms' => 3, 'target' => 40000],
            ['area' => 45, 'rooms' => 2, 'target' => 32000],
        ];

        $accuracy = $method->invoke($this->service, $model, $features);

        $this->assertIsFloat($accuracy);
        $this->assertGreaterThanOrEqual(0, $accuracy);
        $this->assertLessThanOrEqual(1, $accuracy);
    }

    public function test_extract_room_count_handles_various_formats(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractRoomCount');
        $method->setAccessible(true);

        $testCases = [
            '2房' => 2,
            '3房2廳' => 3,
            '1房1廳' => 1,
            '4房' => 4,
            null => 1, // 預設值
            '' => 1,
            'studio' => 1,
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->service, $input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    public function test_encode_district_returns_consistent_values(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('encodeDistrict');
        $method->setAccessible(true);

        // 測試同一個地區應該返回相同的值
        $district1 = $method->invoke($this->service, '中正區');
        $district2 = $method->invoke($this->service, '中正區');

        $this->assertEquals($district1, $district2);
        $this->assertIsFloat($district1);
        $this->assertGreaterThanOrEqual(0, $district1);
        $this->assertLessThanOrEqual(1, $district1);
    }

    public function test_encode_building_type_returns_consistent_values(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('encodeBuildingType');
        $method->setAccessible(true);

        // 測試同一個建築類型應該返回相同的值
        $type1 = $method->invoke($this->service, '公寓');
        $type2 = $method->invoke($this->service, '公寓');

        $this->assertEquals($type1, $type2);
        $this->assertIsFloat($type1);
        $this->assertGreaterThanOrEqual(0, $type1);
        $this->assertLessThanOrEqual(1, $type1);

        // 測試 null 值
        $nullType = $method->invoke($this->service, null);
        $this->assertIsFloat($nullType);
    }
}
