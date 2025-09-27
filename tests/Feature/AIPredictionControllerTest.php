<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIPredictionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 創建測試用戶
        $this->user = User::factory()->create();

        // 創建測試房產資料
        Property::factory()->count(10)->create([
            'rent_per_month' => 25000,
            'total_floor_area' => 30,
            'district' => '中正區',
            'building_type' => '公寓',
        ]);
    }

    public function test_predict_endpoint_works_without_authentication(): void
    {
        $response = $this->postJson('/api/ai-prediction/predict', [
            'property_data' => [
                'area' => 30,
                'district' => '中正區',
                'rent_per_month' => 25000,
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_predict_endpoint_validates_required_fields(): void
    {
        $response = $this->postJson('/api/ai-prediction/predict', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['property_data']);
    }

    public function test_predict_endpoint_returns_prediction_for_valid_data(): void
    {

        $propertyData = [
            'area' => 30,
            'district' => '中正區',
            'rent_per_month' => 25000,
            'building_type' => '公寓',
            'rooms' => 2,
            'floor' => 3,
            'age' => 5,
            'transport_access' => ['捷運'],
            'facilities' => ['超市', '學校'],
            'safety_score' => 85,
        ];

        $response = $this->postJson('/api/ai-prediction/predict', [
            'property_data' => $propertyData,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'prediction',
                'assessed_at',
            ]);

        $responseData = $response->json();
        $this->assertEquals('success', $responseData['status']);
        $this->assertArrayHasKey('prediction', $responseData);
        $this->assertArrayHasKey('predicted_price', $responseData['prediction']);
    }

    public function test_trends_endpoint_returns_market_trends(): void
    {

        $response = $this->getJson('/api/ai-prediction/trends');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'trends',
                'generated_at',
            ]);

        $responseData = $response->json();
        $this->assertEquals('success', $responseData['status']);
    }

    public function test_trends_endpoint_accepts_district_filter(): void
    {

        $response = $this->getJson('/api/ai-prediction/trends?district=中正區');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'trends',
                'district',
                'generated_at',
            ]);

        $responseData = $response->json();
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('中正區', $responseData['district']);
    }

    public function test_dashboard_endpoint_returns_response(): void
    {
        $response = $this->get('/api/ai-prediction/dashboard');

        // 由於沒有 Inertia 頁面，我們只檢查響應狀態
        $response->assertStatus(200);
    }

    public function test_predict_endpoint_handles_invalid_area(): void
    {

        $response = $this->postJson('/api/ai-prediction/predict', [
            'property_data' => [
                'area' => -10, // 無效的面積
                'district' => '中正區',
                'rent_per_month' => 25000,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['property_data.area']);
    }

    public function test_predict_endpoint_handles_invalid_rent(): void
    {

        $response = $this->postJson('/api/ai-prediction/predict', [
            'property_data' => [
                'area' => 30,
                'district' => '中正區',
                'rent_per_month' => -1000, // 無效的租金
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['property_data.rent_per_month']);
    }

    public function test_predict_endpoint_handles_missing_district(): void
    {

        $response = $this->postJson('/api/ai-prediction/predict', [
            'property_data' => [
                'area' => 30,
                'rent_per_month' => 25000,
                // 缺少 district
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['property_data.district']);
    }

    public function test_predict_endpoint_handles_optional_fields(): void
    {

        // 只提供必要欄位
        $minimalData = [
            'area' => 30,
            'district' => '中正區',
            'rent_per_month' => 25000,
        ];

        $response = $this->postJson('/api/ai-prediction/predict', [
            'property_data' => $minimalData,
        ]);

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals('success', $responseData['status']);
        $this->assertArrayHasKey('prediction', $responseData);
    }

    public function test_predict_endpoint_validates_safety_score_range(): void
    {

        $response = $this->postJson('/api/ai-prediction/predict', [
            'property_data' => [
                'area' => 30,
                'district' => '中正區',
                'rent_per_month' => 25000,
                'safety_score' => 150, // 超出範圍 (0-100)
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['property_data.safety_score']);
    }
}
