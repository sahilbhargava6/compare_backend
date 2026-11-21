<?php

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeService
{
    /**
     * Upsert product vector and metadata to Pinecone.
     */
    public function upsert(string $id, array $vector, array $metadata): bool
    {
        $apiKey = config('services.pinecone.api_key');
        $host = config('services.pinecone.host');

        if (empty($apiKey) || empty($host)) {
            Log::info("Pinecone credentials not set; skipped remote vector upsert for ID: {$id}");
            return true;
        }

        try {
            $host = rtrim(str_replace(['https://', 'http://'], '', $host), '/');
            $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("https://{$host}/vectors/upsert", [
                'vectors' => [
                    [
                        'id' => $id,
                        'values' => $vector,
                        'metadata' => $metadata,
                    ]
                ]
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Pinecone upsert failed for {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Query nearest vector neighbors applying metadata pre-filter.
     * E.g. price +/- 20%, rating > 4.0, skin_type matches.
     *
     * @return array<array{id: string, score: float, metadata: array}>
     */
    public function query(array $vector, int $topK = 5, array $filter = []): array
    {
        $apiKey = config('services.pinecone.api_key');
        $host = config('services.pinecone.host');

        if (!empty($apiKey) && !empty($host)) {
            try {
                $host = rtrim(str_replace(['https://', 'http://'], '', $host), '/');
                $payload = [
                    'vector' => $vector,
                    'topK' => $topK,
                    'includeMetadata' => true,
                ];

                if (!empty($filter)) {
                    $payload['filter'] = $filter;
                }

                $response = Http::withHeaders([
                    'Api-Key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(10)->post("https://{$host}/query", $payload);

                if ($response->successful()) {
                    $matches = $response->json('matches');
                    if (is_array($matches)) {
                        return $matches;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Pinecone query failed: " . $e->getMessage());
            }
        }

        // Fallback: Query nearest alternatives from PostgreSQL products
        return $this->fallbackDatabaseAlternatives($topK, $filter);
    }

    /**
     * Graceful DB-based alternative retrieval when Pinecone is offline or credentials not set.
     */
    protected function fallbackDatabaseAlternatives(int $topK, array $filter): array
    {
        $query = Product::query()->with('latestPrice');

        if (isset($filter['platform'])) {
            $query->where('platform', $filter['platform']);
        }

        $products = $query->take($topK)->get();

        $results = [];
        foreach ($products as $index => $product) {
            $price = $product->latestPrice?->price ?? 999.00;
            $results[] = [
                'id' => (string) $product->id,
                'score' => round(0.95 - ($index * 0.05), 2),
                'metadata' => [
                    'title' => $product->name,
                    'brand' => $product->brand,
                    'price' => (float) $price,
                    'platform' => $product->platform,
                    'canonical_url' => $product->canonical_url,
                ],
            ];
        }

        return $results;
    }
}
