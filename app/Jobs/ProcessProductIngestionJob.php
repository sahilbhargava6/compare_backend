<?php

namespace App\Jobs;

use App\Events\ProductIngestedEvent;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\AiCurationService;
use App\Services\AI\EmbeddingService;
use App\Services\AI\PineconeService;
use App\Services\ScraperWaterfallService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessProductIngestionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $url,
        public string $userId
    ) {}

    public function handle(
        ScraperWaterfallService $waterfallService,
        EmbeddingService $embeddingService,
        PineconeService $pineconeService,
        AiCurationService $aiCurationService
    ): void {
        Log::info("Processing ingestion job for URL: {$this->url} (User: {$this->userId})");

        // 1. Execute 3-Tier Waterfall Scraper
        $scrapedData = $waterfallService->scrape($this->url);

        // 2. Persist Product & Price History to PostgreSQL
        $product = Product::updateOrCreate(
            ['canonical_url' => $scrapedData->canonicalUrl],
            [
                'brand' => $scrapedData->brand,
                'name' => $scrapedData->title,
                'platform' => $scrapedData->platform,
                'vector_id' => $scrapedData->canonicalUrl,
            ]
        );

        PriceHistory::create([
            'product_id' => $product->id,
            'price' => $scrapedData->price,
            'recorded_at' => now(),
        ]);

        // 3. Generate Embedding and Upsert to Pinecone
        $embeddingText = "{$scrapedData->title} {$scrapedData->brand} {$scrapedData->platform} " . json_encode($scrapedData->attributes);
        $vector = $embeddingService->generateEmbedding($embeddingText);

        $metadata = [
            'product_id' => (string) $product->id,
            'title' => $scrapedData->title,
            'brand' => $scrapedData->brand,
            'price' => $scrapedData->price,
            'platform' => $scrapedData->platform,
            'canonical_url' => $scrapedData->canonicalUrl,
        ];

        $pineconeService->upsert((string) $product->id, $vector, $metadata);

        // 4. Query Pinecone for Nearest Neighbors (Price +/- 20%)
        $priceMin = $scrapedData->price * 0.8;
        $priceMax = $scrapedData->price * 1.2;
        $filter = [
            'price' => ['$gte' => $priceMin, '$lte' => $priceMax],
        ];

        $candidateAlternatives = $pineconeService->query($vector, 5, $filter);

        // 5. Fetch User Profile Preferences and Run AI Curation
        $user = User::find($this->userId);
        $preferences = $user?->preferences ?? ['skin_type' => 'all'];

        $curation = $aiCurationService->curateAndAnalyze($scrapedData, $preferences, $candidateAlternatives);

        // 6. Cache comprehensive result in Redis (1 day TTL)
        $cacheKey = 'scraped_product:' . md5($this->url);
        $payload = [
            'product_id' => $product->id,
            'product' => $scrapedData->toArray(),
            'curation' => $curation,
        ];
        Cache::put($cacheKey, $payload, 86400);

        // 7. Broadcast WebSocket event to Next.js frontend via Reverb/Pusher
        event(new ProductIngestedEvent(
            userId: $this->userId,
            productData: $scrapedData,
            productId: (string) $product->id,
            curation: $curation
        ));
    }
}
