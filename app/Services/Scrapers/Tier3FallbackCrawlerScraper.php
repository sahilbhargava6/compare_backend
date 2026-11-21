<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperInterface;
use App\DTOs\ScrapedProductData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Tier3FallbackCrawlerScraper implements ScraperInterface
{
    public function getTierName(): string
    {
        return 'Tier 3 - ScraperAPI + DOM Crawler';
    }

    public function supports(string $url): bool
    {
        return true; // Catch-all fallback
    }

    public function extract(string $url): ScrapedProductData
    {
        $platform = str_contains($url, 'amazon')
            ? 'amazon'
            : (str_contains($url, 'nykaa') ? 'nykaa' : (str_contains($url, 'flipkart') ? 'flipkart' : 'general'));

        $apiKey = config('services.scrapers.tier3.scraper_api.api_key');

        $html = '';
        if (!empty($apiKey)) {
            try {
                $response = Http::timeout(25)->get('https://api.scraperapi.com', [
                    'api_key' => $apiKey,
                    'url' => $url,
                ]);

                if ($response->successful()) {
                    $html = $response->body();
                }
            } catch (\Throwable $e) {
                Log::warning("ScraperAPI request failed: " . $e->getMessage());
            }
        }

        // If no ScraperAPI or it failed, attempt a direct fetch with user agent
        if (empty($html)) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])->timeout(10)->get($url);

                if ($response->successful()) {
                    $html = $response->body();
                }
            } catch (\Throwable $e) {
                Log::warning("Direct crawler fetch failed: " . $e->getMessage());
            }
        }

        // Parse meta tags if HTML was fetched
        $title = $this->extractMeta($html, 'og:title') ?: $this->extractTag($html, 'title') ?: "Discovered {$platform} Item";
        $image = $this->extractMeta($html, 'og:image') ?: "https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=500";
        $price = $this->extractPriceFromHtml($html) ?: 999.00;
        $brand = $this->extractMeta($html, 'product:brand') ?: $this->extractBrandFromName($title);

        return new ScrapedProductData(
            title: trim(strip_tags($title)),
            brand: trim(strip_tags($brand)),
            price: $price,
            platform: $platform,
            canonicalUrl: $url,
            imageUrl: $image,
            rating: 4.2,
            attributes: [
                'skin_type' => 'all',
                'category' => 'skincare',
                'extracted_at' => now()->toIso8601String(),
            ],
            tierUsed: $this->getTierName()
        );
    }

    protected function extractMeta(string $html, string $property): ?string
    {
        if (empty($html)) {
            return null;
        }

        if (preg_match('/<meta\s+property=["\']' . preg_quote($property, '/') . '["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }

        if (preg_match('/<meta\s+name=["\']' . preg_quote($property, '/') . '["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }

        return null;
    }

    protected function extractTag(string $html, string $tag): ?string
    {
        if (preg_match('/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/is', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        return null;
    }

    protected function extractPriceFromHtml(string $html): ?float
    {
        if (empty($html)) {
            return null;
        }

        // Match common price patterns like ₹ 1,299 or Rs. 1299 or price: 1299
        if (preg_match('/(?:₹|Rs\.?|INR)\s*([0-9,]+(?:\.[0-9]{2})?)/i', $html, $matches)) {
            $clean = str_replace(',', '', $matches[1]);
            return (float) $clean;
        }

        return null;
    }

    protected function extractBrandFromName(string $title): string
    {
        $parts = explode(' ', trim($title));
        return count($parts) > 0 && !empty($parts[0]) ? $parts[0] : 'Curated Brand';
    }
}
