<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AI\SkinAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkinAnalysisController extends Controller
{
    public function __construct(
        protected SkinAnalysisService $skinAnalysisService
    ) {}

    /**
     * Analyze a face capture/video frame and generate a dermatological diagnosis and routine.
     */
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'string'], // base64 data URI or public image URL
            'user_id' => ['nullable', 'string'],
            'sync_profile' => ['nullable', 'boolean'],
        ]);

        $analysis = $this->skinAnalysisService->analyzeSkinImage($validated['image']);

        // Optionally synchronize the diagnosed skin profile with the User model
        $userId = $validated['user_id'] ?? null;
        if ($userId && ($validated['sync_profile'] ?? true)) {
            $user = User::find($userId);
            if ($user) {
                $preferences = $user->preferences ?? [];
                $preferences['skin_type'] = $analysis['diagnosed_skin_type'] ?? 'combination';
                $preferences['skin_health_score'] = $analysis['skin_health_score'] ?? 78;
                $preferences['primary_concerns'] = $analysis['primary_concerns'] ?? [];
                $preferences['metrics'] = $analysis['metrics'] ?? [];
                $preferences['last_skin_scan'] = now()->toIso8601String();
                
                $user->preferences = $preferences;
                $user->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Facial skin diagnostics completed successfully.',
            'diagnosis' => $analysis,
        ]);
    }
}
