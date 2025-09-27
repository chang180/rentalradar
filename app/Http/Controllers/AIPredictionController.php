<?php

namespace App\Http\Controllers;

use App\Services\AIModelTrainingService;
use App\Services\PredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AIPredictionController extends Controller
{
    private AIModelTrainingService $trainingService;

    private PredictionService $predictionService;

    public function __construct(AIModelTrainingService $trainingService, PredictionService $predictionService)
    {
        $this->trainingService = $trainingService;
        $this->predictionService = $predictionService;
    }

    /**
     * 訓練 AI 模型
     */
    public function trainModel(): JsonResponse
    {
        try {
            $result = $this->trainingService->trainRentPredictionModel();

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Model training failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 預測租金價格
     */
    public function predict(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'property_data' => 'required|array',
                'property_data.area' => 'required|numeric|min:1',
                'property_data.district' => 'required|string',
                'property_data.total_rent' => 'required|numeric|min:0',
                'property_data.building_type' => 'nullable|string',
                'property_data.rooms' => 'nullable|integer|min:1',
                'property_data.floor' => 'nullable|integer|min:1',
                'property_data.age' => 'nullable|integer|min:0',
                'property_data.transport_access' => 'nullable|array',
                'property_data.facilities' => 'nullable|array',
                'property_data.safety_score' => 'nullable|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $propertyData = $request->input('property_data');
            $prediction = $this->predictionService->predictRentPrice($propertyData);

            return response()->json([
                'status' => 'success',
                'prediction' => $prediction,
                'assessed_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Prediction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取市場趨勢
     */
    public function trends(Request $request): JsonResponse
    {
        try {
            $district = $request->query('district');
            $trends = $this->predictionService->getMarketTrends($district);

            return response()->json([
                'status' => 'success',
                'trends' => $trends,
                'district' => $district,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Trends analysis failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 儀表板資料
     */
    public function dashboard(): JsonResponse
    {
        try {
            $modelInfo = $this->trainingService->getModelInfo();
            $trends = $this->predictionService->getMarketTrends();

            return response()->json([
                'status' => 'success',
                'model_info' => $modelInfo,
                'market_trends' => $trends,
                'page_title' => 'AI 預測儀表板',
                'last_updated' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '無法載入 AI 預測儀表板：'.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取模型資訊
     */
    public function getModelInfo(): JsonResponse
    {
        try {
            $info = $this->trainingService->getModelInfo();

            return response()->json($info);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get model info: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 預測租金價格
     */
    public function predictRent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'area' => 'required|numeric|min:1',
            'rooms' => 'required|integer|min:1',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'district' => 'required|string|max:50',
            'building_type' => 'nullable|string|max:50',
            'rent_date' => 'nullable|date',
            'property_id' => 'nullable|integer|exists:properties,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $propertyData = $request->only([
                'area', 'rooms', 'latitude', 'longitude',
                'district', 'building_type', 'rent_date', 'property_id',
            ]);

            $result = $this->predictionService->predictRentPrice($propertyData);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Prediction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 預測特定物件的租金
     */
    public function predictProperty(int $propertyId): JsonResponse
    {
        try {
            $result = $this->predictionService->predictPropertyValue($propertyId);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Property prediction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 批量預測
     */
    public function batchPredict(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'properties' => 'required|array|max:50',
            'properties.*.area' => 'required|numeric|min:1',
            'properties.*.rooms' => 'required|integer|min:1',
            'properties.*.latitude' => 'required|numeric|between:-90,90',
            'properties.*.longitude' => 'required|numeric|between:-180,180',
            'properties.*.district' => 'required|string|max:50',
            'properties.*.building_type' => 'nullable|string|max:50',
            'properties.*.rent_date' => 'nullable|date',
            'properties.*.property_id' => 'nullable|integer|exists:properties,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $properties = $request->input('properties');
            $result = $this->predictionService->batchPredict($properties);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Batch prediction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取市場趨勢
     */
    public function getMarketTrends(Request $request): JsonResponse
    {
        $district = $request->input('district');

        try {
            $trends = $this->predictionService->getMarketTrends($district);

            return response()->json($trends);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get market trends: '.$e->getMessage(),
            ], 500);
        }
    }
}
