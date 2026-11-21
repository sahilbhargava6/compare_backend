<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductIngestionJob;
use App\Models\User;
use App\Services\UrlSanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProductIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_sanitizer_strips_tracking_params(): void
    {
        $sanitizer = new UrlSanitizerService();
        $dirtyUrl = 'https://www.amazon.in/dp/B08N5WRWNW?tag=myaffiliate-21&ref=sr_1_1&utm_source=twitter';
        $cleanUrl = $sanitizer->sanitize($dirtyUrl);

        $this->assertEquals('https://www.amazon.in/dp/B08N5WRWNW', $cleanUrl);
    }

    public function test_ingest_dispatches_job_and_returns_202_accepted(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/products/ingest', [
            'url' => 'https://www.amazon.in/dp/B08N5WRWNW?tag=tracking-20',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'status',
                'message',
                'canonical_url',
                'user_id',
                'websocket_channel',
                'websocket_event',
            ])
            ->assertJson([
                'status' => 'queued',
                'canonical_url' => 'https://www.amazon.in/dp/B08N5WRWNW',
                'websocket_event' => 'product.ingested',
            ]);

        Queue::assertPushed(ProcessProductIngestionJob::class);
    }

    public function test_cached_product_returns_200_instantly(): void
    {
        $url = 'https://www.amazon.in/dp/B08N5WRWNW';
        $cacheKey = 'scraped_product:' . md5($url);

        Cache::put($cacheKey, [
            'product' => ['title' => 'Cached Product', 'price' => 799.00],
            'curation' => ['pros' => ['Fast delivery']],
        ], 3600);

        $response = $this->postJson('/api/products/ingest', [
            'url' => $url,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'cached',
            ]);
    }
}
