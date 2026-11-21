<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    /**
     * Get user profile and preferences.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'preferences' => $user->preferences ?? [
                'skin_type' => 'normal',
                'allergies' => [],
            ],
        ]);
    }

    /**
     * Update user preferences (e.g. skin_type, allergies, sensitivities).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.skin_type' => ['nullable', 'string'],
            'preferences.allergies' => ['nullable', 'array'],
            'name' => ['nullable', 'string'],
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        $currentPrefs = $user->preferences ?? [];
        $user->preferences = array_merge($currentPrefs, $validated['preferences']);
        $user->save();

        return response()->json([
            'message' => 'User profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'preferences' => $user->preferences,
            ],
        ]);
    }
}
