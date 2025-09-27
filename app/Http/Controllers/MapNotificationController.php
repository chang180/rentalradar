<?php

namespace App\Http\Controllers;

use App\Events\RealTimeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapNotificationController extends Controller
{
    public function sendNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:255',
            'type' => 'string|in:info,success,warning,error',
            'user_id' => 'nullable|exists:users,id',
            'data' => 'nullable|array',
        ]);

        $notification = new RealTimeNotification(
            message: $validated['message'],
            type: $validated['type'] ?? 'info',
            data: $validated['data'] ?? null,
            userId: $validated['user_id'] ?? null
        );

        broadcast($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully',
        ]);
    }
}
