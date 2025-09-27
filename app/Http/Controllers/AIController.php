<?php

namespace App\Http\Controllers;

use App\Services\AIMapOptimizationService;
use App\Support\PerformanceMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class AIController extends Controller
{
    protected AIMapOptimizationService $aiService;

    public function __construct(AIMapOptimizationService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * 執行 AI 分析
     */
    public function analyze(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('ai.analyze');
        
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:anomaly_detection,price_prediction,heatmap,clustering',
            'data' => 'required|array',
            'parameters' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request parameters',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $type = $request->input('type');
        $data = $request->input('data');
        $parameters = $request->input('parameters', []);

        $monitor->mark('validation_complete');

        // 建立快取鍵
        $cacheKey = 'ai_analysis_' . md5(json_encode($request->all()));

        // 檢查快取
        $cached = Cache::get($cacheKey);
        if ($cached) {
            $monitor->mark('cache_hit');
            return response()->json([
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'performance' => $monitor->summary()
            ]);
        }

        $monitor->mark('cache_miss');

        try {
            $result = $monitor->trackModel($type, function () use ($type, $data, $parameters) {
                return match ($type) {
                    'anomaly_detection' => $this->aiService->detectAnomalies($data, $parameters),
                    'price_prediction' => $this->aiService->predictPrices($data, $parameters),
                    'heatmap' => $this->aiService->generateHeatmap($data, $parameters),
                    'clustering' => $this->aiService->clusteringAlgorithm($data, $parameters['algorithm'] ?? 'kmeans'),
                    default => throw new \InvalidArgumentException('Unsupported analysis type')
                };
            }, ['threshold_ms' => 500]);

            if (!$result['success']) {
                $monitor->addWarning('AI service failed', ['error' => $result['error']]);
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'AI_SERVICE_ERROR',
                        'message' => $result['error']
                    ],
                    'performance' => $monitor->summary()
                ], 500);
            }

            $monitor->mark('analysis_complete');

            // 快取結果
            Cache::put($cacheKey, $result['data'], 3600); // 1小時快取

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'performance' => $monitor->summary()
            ]);

        } catch (\Exception $e) {
            $monitor->addWarning('AI analysis exception', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AI_SERVICE_ERROR',
                    'message' => $e->getMessage()
                ],
                'performance' => $monitor->summary()
            ], 500);
        }
    }

    /**
     * 檢查 AI 服務狀態
     */
    public function status(): JsonResponse
    {
        $status = [
            'available' => true,
            'version' => 'v1.0',
            'ready' => true,
            'features' => [
                'clustering' => true,
                'heatmap' => true,
                'price_prediction' => true,
                'anomaly_detection' => true
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * 執行異常值檢測
     */
    public function detectAnomalies(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'method' => 'sometimes|string|in:zscore,iqr',
            'threshold' => 'sometimes|numeric|min:0|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request parameters',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $result = $this->aiService->detectAnomalies(
            $request->input('data'),
            $request->only(['method', 'threshold'])
        );

        return response()->json($result);
    }

    /**
     * 執行價格預測
     */
    public function predictPrices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'model_version' => 'sometimes|string',
            'confidence_threshold' => 'sometimes|numeric|min:0|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request parameters',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $result = $this->aiService->predictPrices(
            $request->input('data'),
            $request->only(['model_version', 'confidence_threshold'])
        );

        return response()->json($result);
    }

    /**
     * 生成熱力圖資料
     */
    public function generateHeatmap(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'resolution' => 'sometimes|string|in:low,medium,high',
            'color_scheme' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request parameters',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $result = $this->aiService->generateHeatmap(
            $request->input('data'),
            $request->only(['resolution', 'color_scheme'])
        );

        return response()->json($result);
    }
}
