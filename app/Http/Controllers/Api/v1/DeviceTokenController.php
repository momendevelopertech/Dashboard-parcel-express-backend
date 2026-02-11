<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    /**
     * Register or update FCM token for the authenticated driver.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string|in:android,ios,web',
        ]);

        $user = Auth::user();
        $token = $request->input('token');
        $platform = $request->input('platform', 'unknown');

        try {
            // Step 1: Find if this token exists for ANY user
            $existingToken = DeviceToken::where('token', $token)->first();

            if ($existingToken && $existingToken->user_id !== $user->id) {
                Log::info("FCM Token transfer: from user {$existingToken->user_id} to user {$user->id}", [
                    'token' => substr($token, 0, 20) . '...',
                    'old_user_id' => $existingToken->user_id,
                    'new_user_id' => $user->id,
                ]);
            }

            // Step 2: Update or create the token for current user
            DeviceToken::updateOrCreate(
                ['token' => $token],
                [
                    'user_id' => $user->id,
                    'platform' => $platform,
                ]
            );

            // Step 3: Subscribe to driver topic
            try {
                /** @var FcmService $fcm */
                $fcm = resolve(FcmService::class);
                $fcm->subscribeTokenToDriverTopic($user->id, $token);
            } catch (\Throwable $e) {
                Log::warning('FCM topic subscription failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'FCM token registered successfully',
                'data' => [
                    'user_id' => $user->id,
                    'platform' => $platform,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('FCM token registration failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Remove FCM token for the authenticated driver (logout).
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = Auth::user();
        $token = $request->input('token');

        try {
            // Remove the token from database
            $deleted = DeviceToken::where('token', $token)
                ->where('user_id', $user->id)
                ->delete();

            if ($deleted) {
                Log::info("FCM Token removed for user {$user->id}");
            }

            return response()->json([
                'success' => true,
                'message' => 'FCM token removed successfully',
            ]);

        } catch (\Throwable $e) {
            Log::error('FCM token removal failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove FCM token',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}
