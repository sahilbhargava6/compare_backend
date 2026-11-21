<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WatchlistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_watchlist_crud_lifecycle(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'preferences' => ['skin_type' => 'oily'],
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'canonical_url' => 'https://www.nykaa.com/product/12345',
            'brand' => 'Minimalist',
            'name' => 'Niacinamide 10%',
            'platform' => 'nykaa',
        ]);

        // 1. Add to watchlist
        $postResponse = $this->postJson('/api/watchlist', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'target_price' => 499.00,
        ]);

        $postResponse->assertStatus(201)
            ->assertJsonPath('watchlist.target_price', '499.00');

        $watchlistId = $postResponse->json('watchlist.id');

        // 2. Fetch watchlist
        $getResponse = $this->getJson("/api/watchlist?user_id={$user->id}");
        $getResponse->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('watchlists.0.product.name', 'Niacinamide 10%');

        // 3. Update target price
        $putResponse = $this->putJson("/api/watchlist/{$watchlistId}", [
            'target_price' => 450.00,
            'is_active' => false,
        ]);

        $putResponse->assertStatus(200)
            ->assertJsonPath('watchlist.target_price', '450.00')
            ->assertJsonPath('watchlist.is_active', false);

        // 4. Delete item
        $deleteResponse = $this->deleteJson("/api/watchlist/{$watchlistId}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('watchlists', ['id' => $watchlistId]);
    }

    public function test_user_profile_management(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'preferences' => ['skin_type' => 'dry', 'allergies' => ['salicylic acid']],
        ]);

        $getResponse = $this->getJson("/api/users/{$user->id}/profile");
        $getResponse->assertStatus(200)
            ->assertJsonPath('preferences.skin_type', 'dry');

        $putResponse = $this->putJson("/api/users/{$user->id}/profile", [
            'preferences' => [
                'skin_type' => 'combination',
                'allergies' => ['fragrance', 'alcohol'],
            ],
        ]);

        $putResponse->assertStatus(200)
            ->assertJsonPath('user.preferences.skin_type', 'combination')
            ->assertJsonPath('user.preferences.allergies', ['fragrance', 'alcohol']);
    }
}
