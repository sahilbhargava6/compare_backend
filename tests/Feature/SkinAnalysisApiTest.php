<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkinAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_skin_analyze_returns_structured_dermatological_report(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sophia',
            'email' => 'sophia@example.com',
            'preferences' => ['skin_type' => 'normal'],
        ]);

        $response = $this->postJson('/api/skin/analyze', [
            'image' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBD...',
            'user_id' => $user->id,
            'sync_profile' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'diagnosis' => [
                    'skin_health_score',
                    'diagnosed_skin_type',
                    'display_skin_type',
                    'metrics' => [
                        'hydration',
                        'sebum_t_zone',
                        'redness',
                        'texture',
                        'biological_skin_age',
                    ],
                    'primary_concerns',
                    'recommended_actives',
                    'prescribed_routine' => [
                        'step_1_balance',
                        'step_2_soothe',
                        'step_3_replenish',
                    ],
                    'curated_products',
                ],
            ]);

        // Verify that user profile preferences were automatically synced
        $user->refresh();
        $this->assertEquals('combination-acne-prone', $user->preferences['skin_type']);
        $this->assertEquals(78, $user->preferences['skin_health_score']);
        $this->assertNotEmpty($user->preferences['primary_concerns']);
    }
}
