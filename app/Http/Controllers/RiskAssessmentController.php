<?php

namespace App\Http\Controllers;

use App\Services\RiskAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class RiskAssessmentController extends Controller
{
    protected RiskAssessmentService $riskAssessmentService;

    public function __construct(RiskAssessmentService $riskAssessmentService)
    {
        $this->riskAssessmentService = $riskAssessmentService;
    }

    public function assess(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
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

            $propertyData = $request->input('property_data');
            $assessment = $this->riskAssessmentService->assessInvestmentRisk($propertyData);

            return response()->json([
                'status' => 'success',
                'assessment' => $assessment,
                'assessed_at' => now()->toISOString(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Risk assessment failed', [
                'user_id' => Auth::id(),
                'property_data' => $request->input('property_data'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Risk assessment failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function trends(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $district = $request->query('district');

            $trends = $this->riskAssessmentService->getRiskTrends($district);

            return response()->json([
                'status' => 'success',
                'trends' => $trends,
                'district' => $district,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Risk trends analysis failed', [
                'district' => $request->query('district'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Risk trends analysis failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function dashboard(): \Inertia\Response
    {
        try {
            // 獲取風險評估儀表板資料
            $trends = $this->riskAssessmentService->getRiskTrends();
            $performanceMetrics = [
                'status' => 'active',
                'last_updated' => now()->toISOString(),
                'model_version' => '1.0.0',
            ];

            return Inertia::render('RiskAssessmentDashboard', [
                'trends' => $trends,
                'performance_metrics' => $performanceMetrics,
                'page_title' => '風險評估儀表板',
                'last_updated' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Risk assessment dashboard failed', [
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('RiskAssessmentDashboard', [
                'trends' => [],
                'performance_metrics' => [],
                'page_title' => '風險評估儀表板',
                'error' => '載入儀表板資料時發生錯誤',
            ]);
        }
    }

    public function batchAssess(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'properties' => 'required|array|min:1|max:50',
                'properties.*.area' => 'required|numeric|min:1',
                'properties.*.district' => 'required|string',
                'properties.*.total_rent' => 'required|numeric|min:0',
            ]);

            $properties = $request->input('properties');
            $assessments = [];

            foreach ($properties as $index => $propertyData) {
                try {
                    $assessment = $this->riskAssessmentService->assessInvestmentRisk($propertyData);
                    $assessments[] = [
                        'index' => $index,
                        'status' => 'success',
                        'assessment' => $assessment,
                    ];
                } catch (\Exception $e) {
                    $assessments[] = [
                        'index' => $index,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'assessments' => $assessments,
                'total_assessed' => count($assessments),
                'successful_assessments' => count(array_filter($assessments, fn ($a) => $a['status'] === 'success')),
                'assessed_at' => now()->toISOString(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Batch risk assessment failed', [
                'user_id' => Auth::id(),
                'properties_count' => count($request->input('properties', [])),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Batch risk assessment failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
