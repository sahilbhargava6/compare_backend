<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperInterface;
use App\DTOs\ScrapedProductData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Tier2ManagedApiScraper implements ScraperInterface
{
    public function getTierName(): string
    {
        return 'Tier 2 - Managed JSON API (Rainforest/Apify)';
    }

    public function supports(string $url): bool
    {
        return true; // Supports Amazon (via Rainforest) and Nykaa/others (via Apify)
    }

    public function extract(string $url): ScrapedProductData
    {
        if (str_contains($url, 'amazon.')) {
            return $this->extractViaRainforest($url);
        }

        if (str_contains($url, 'nykaa.')) {
            return $this->extractViaApifyNykaa($url);
        }

        throw new RuntimeException("Tier 2 does not have a managed API configured for URL: {$url}");
    }

    protected function extractViaRainforest(string $url): ScrapedProductData
    {
        $apiKey = config('services.scrapers.tier2.rainforest.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException("Rainforest API key is not configured.");
        }

        try {
            $response = Http::timeout(15)->get('https://api.rainforestapi.com/request', [
                'api_key' => $apiKey,
                'type' => 'product',
                'url' => $url,
            ]);

            if (!$response->successful()) {
                throw new RuntimeException("Rainforest API responded with status {$response->status()}");
            }

            $product = $response->json('product');
            if (empty($product)) {
                throw new RuntimeException("No product payload returned by Rainforest API.");
            }

            $title = $product['title'] ?? 'Amazon Product';
            $brand = $product['brand'] ?? 'Unknown Brand';
            $price = (float) ($product['buybox_winner']['price']['value'] ?? $product['price']['value'] ?? 0.0);
            $imageUrl = $product['main_image']['link'] ?? null;
            $rating = (float) ($product['rating'] ?? 0.0);
            $features = $product['feature_bullets'] ?? [];

            return new ScrapedProductData(
                title: $title,
                brand: $brand,
                price: $price,
                platform: 'amazon',
                canonicalUrl: $product['link'] ?? $url,
                imageUrl: $imageUrl,
                rating: $rating,
                attributes: [
                    'features' => $features,
                    'ratings_total' => $product['ratings_total'] ?? null,
                ],
                tierUsed: $this->getTierName()
            );
        } catch (\Throwable $e) {
            Log::warning("Rainforest extraction failed: " . $e->getMessage());
            throw new RuntimeException("Rainforest API failed: " . $e->getMessage(), 0, $e);
        }
    }

    protected function extractViaApifyNykaa(string $url): ScrapedProductData
    {
        $apiToken = config('services.scrapers.tier2.apify.api_token');
        $actorId = config('services.scrapers.tier2.apify.nykaa_actor_id', 'apify/web-scraper');

        if (empty($apiToken)) {
            throw new RuntimeException("Apify API token is not configured.");
        }

        try {
            $response = Http::withToken($apiToken)
                ->timeout(20)
                ->post("https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items", [
                    'startUrls' => [['url' => $url]],
                    'maxItems' => 1,
                ]);

            if (!$response->successful()) {
                throw new RuntimeException("Apify actor run failed with status {$response->status()}");
            }

            $items = $response->json();
            $item = is_array($items) && isset($items[0]) ? $items[0] : null;

            if (!$item) {
                throw new RuntimeException("No item returned by Apify actor for URL: {$url}");
            }

            return new ScrapedProductData(
                title: $item['title'] ?? $item['name'] ?? 'Nykaa Product',
                brand: $item['brand'] ?? 'Unknown Brand',
                price: (float) ($item['price'] ?? $item['final_price'] ?? 0.0),
                platform: 'nykaa',
                canonicalUrl: $url,
                imageUrl: $item['image'] ?? $item['image_url'] ?? null,
                rating: isset($item['rating']) ? (float) $item['rating'] : null,
                attributes: $item['attributes'] ?? ['skin_type' => $item['skin_type'] ?? 'all'],
                tierUsed: $this->getTierName()
            );
        } catch (\Throwable $e) {
            Log::warning("Apify extraction failed: " . $e->getMessage());
            throw new RuntimeException("Apify extraction failed: " . $e->getMessage(), 0, $e);
        }
    }
}
