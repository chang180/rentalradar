<?php

namespace App\Http\Controllers;

use App\Services\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    private RecommendationEngine $recommendationEngine;

    public function __construct(RecommendationEngine $recommendationEngine)
    {
        $this->recommendationEngine = $recommendationEngine;
    }

    /**
     * 獲取個人化推薦
     */
    public function getPersonalizedRecommendations(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
            ], 401);
        }

        $limit = min($request->input('limit', 10), 20); // 最多20個推薦

        try {
            $result = $this->recommendationEngine->generatePersonalizedRecommendations($user, $limit);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get personalized recommendations: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取熱門推薦
     */
    public function getTrendingRecommendations(Request $request): JsonResponse
    {
        $limit = min($request->input('limit', 10), 20);

        try {
            $result = $this->recommendationEngine->getTrendingRecommendations($limit);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get trending recommendations: '.$e->getMessage(),
            ], 500);
        }
    }
}
