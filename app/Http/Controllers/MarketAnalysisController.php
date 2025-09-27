<?php

namespace App\Http\Controllers;

use App\Services\MarketAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketAnalysisController extends Controller
{
    public function __construct(private readonly MarketAnalysisService $analysisService)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'time_range' => ['sometimes', 'string', 'regex:/^\d+[dwmy]$/i'],
            'district' => ['sometimes', 'string', 'max:255'],
            'building_type' => ['sometimes', 'string', 'max:255'],
        ]);

        $data = $this->analysisService->getDashboardData($filters);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'time_range' => ['sometimes', 'string', 'regex:/^\d+[dwmy]$/i'],
            'district' => ['sometimes', 'string', 'max:255'],
            'building_type' => ['sometimes', 'string', 'max:255'],
        ]);

        $report = $this->analysisService->generateReport($filters);

        return response()->json([
            'success' => true,
            'report' => $report,
        ]);
    }
}

