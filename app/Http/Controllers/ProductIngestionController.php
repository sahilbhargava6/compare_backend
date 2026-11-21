<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProductIngestionJob;
use App\Models\Product;
use App\Models\User;
use App\Services\UrlSanitizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductIngestionController extends Controller
{
    public function __construct(
        protected UrlSanitizerService $urlSanitizer
    ) {}

    /**
     * Ingest product URL asynchronously (3-tier waterfall).
     */
    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
            'user_id' => ['nullable', 'string'],
        ]);

        $rawUrl = $validated['url'];
        $sanitizedUrl = $this->urlSanitizer->sanitize($rawUrl);

        // Resolve or create a fallback user if not authenticated
        $userId = $validated['user_id'] ?? null;
        if (!$userId) {
            $user = User::firstOrCreate(
                ['email' => 'guest@tracker.internal'],
                [
                    'id' => (string) Str::uuid(),
                    'name' => 'Guest User',
                    'preferences' => ['skin_type' => 'combination', 'allergies' => []],
                ]
            );
            $userId = (string) $user->id;
        }

        // 1. Check Redis Cache
        $cacheKey = 'scraped_product:' . md5($sanitizedUrl);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return response()->json([
                'status' => 'cached',
                'message' => 'Product retrieved instantly from Redis cache.',
                'data' => $cachedData,
            ], 200);
        }

        // 2. Dispatch Laravel Queue Job and return 202 Accepted
        ProcessProductIngestionJob::dispatch($sanitizedUrl, $userId);

        return response()->json([
            'status' => 'queued',
            'message' => 'Product ingestion dispatched to queue.',
            'canonical_url' => $sanitizedUrl,
            'user_id' => $userId,
            'websocket_channel' => "user.{$userId}",
            'websocket_event' => 'product.ingested',
        ], 202);
    }

    /**
     * Get product details with price history.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with(['priceHistories' => function ($query) {
            $query->orderBy('recorded_at', 'asc');
        }])->findOrFail($id);

        return response()->json([
            'product' => $product,
            'latest_price' => $product->priceHistories->last()?->price,
        ]);
    }
}
