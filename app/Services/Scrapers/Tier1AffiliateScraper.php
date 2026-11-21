<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperInterface;
use App\DTOs\ScrapedProductData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Tier1AffiliateScraper implements ScraperInterface
{
    public function getTierName(): string
    {
        return 'Tier 1 - Official Affiliate API';
    }

    public function supports(string $url): bool
    {
        return str_contains($url, 'amazon.') || str_contains($url, 'flipkart.');
    }

    public function extract(string $url): ScrapedProductData
    {
        if (str_contains($url, 'amazon.')) {
            return $this->extractFromAmazon($url);
        }

        if (str_contains($url, 'flipkart.')) {
            return $this->extractFromFlipkart($url);
        }

        throw new RuntimeException("Tier 1 does not support platform for URL: {$url}");
    }

    protected function extractFromAmazon(string $url): ScrapedProductData
    {
        $config = config('services.scrapers.tier1.amazon_paapi');

        if (empty($config['access_key']) || empty($config['secret_key']) || empty($config['partner_tag'])) {
            throw new RuntimeException("Amazon PA-API credentials are not configured.");
        }

        preg_match('/(?:dp|gp\/product|\/d)\/([A-Z0-9]{10})/i', $url, $matches);
        $asin = $matches[1] ?? null;

        if (!$asin) {
            throw new RuntimeException("Could not extract ASIN from Amazon URL: {$url}");
        }

        $endpoint = "https://{$config['host']}/paapi5/getitems";
        $payload = [
            'ItemIds' => [$asin],
            'Resources' => [
                'ItemInfo.Title',
                'ItemInfo.ByLineInfo',
                'ItemInfo.Features',
                'Offers.Listings.Price',
                'Images.Primary.Large',
            ],
            'PartnerTag' => $config['partner_tag'],
            'PartnerType' => 'Associates',
            'Marketplace' => 'www.amazon.in',
        ];

        try {
            $response = Http::timeout(10)->post($endpoint, $payload);

            if (!$response->successful()) {
                throw new RuntimeException("Amazon PA-API returned status {$response->status()}: " . $response->body());
            }

            $item = $response->json('ItemsResult.Items.0');
            if (!$item) {
                throw new RuntimeException("Item {$asin} not found in Amazon PA-API response.");
            }

            $title = $item['ItemInfo']['Title']['DisplayValue'] ?? 'Amazon Product';
            $brand = $item['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'] ?? 'Unknown Brand';
            $price = (float) ($item['Offers']['Listings'][0]['Price']['Amount'] ?? 0.0);
            $imageUrl = $item['Images']['Primary']['Large']['URL'] ?? null;
            $features = $item['ItemInfo']['Features']['DisplayValues'] ?? [];

            return new ScrapedProductData(
                title: $title,
                brand: $brand,
                price: $price,
                platform: 'amazon',
                canonicalUrl: "https://www.amazon.in/dp/{$asin}",
                imageUrl: $imageUrl,
                rating: null,
                attributes: ['features' => $features],
                tierUsed: $this->getTierName()
            );
        } catch (\Throwable $e) {
            Log::warning("Amazon PA-API extraction failed: " . $e->getMessage());
            throw new RuntimeException("Amazon PA-API failed: " . $e->getMessage(), 0, $e);
        }
    }

    protected function extractFromFlipkart(string $url): ScrapedProductData
    {
        $config = config('services.scrapers.tier1.flipkart');

        if (empty($config['affiliate_id']) || empty($config['token'])) {
            throw new RuntimeException("Flipkart Affiliate API credentials are not configured.");
        }

        preg_match('/pid=([A-Z0-9]+)/i', $url, $matches);
        $productId = $matches[1] ?? null;

        if (!$productId && preg_match('/\/p\/([A-Za-z0-9]+)/i', $url, $matchesPath)) {
            $productId = $matchesPath[1];
        }

        if (!$productId) {
            throw new RuntimeException("Could not extract Product ID from Flipkart URL: {$url}");
        }

        $endpoint = "https://affiliate-api.flipkart.net/affiliate/1.0/product.json?id={$productId}";

        try {
            $response = Http::withHeaders([
                'Fk-Affiliate-Id' => $config['affiliate_id'],
                'Fk-Affiliate-Token' => $config['token'],
            ])->timeout(10)->get($endpoint);

            if (!$response->successful()) {
                throw new RuntimeException("Flipkart Affiliate API returned status {$response->status()}");
            }

            $data = $response->json();
            $baseInfo = $data['productBaseInfo']['productAttributes'] ?? [];

            $title = $baseInfo['title'] ?? 'Flipkart Product';
            $brand = $baseInfo['productBrand'] ?? 'Unknown Brand';
            $price = (float) ($baseInfo['maximumRetailPrice']['amount'] ?? $baseInfo['sellingPrice']['amount'] ?? 0.0);
            $imageUrl = $baseInfo['imageUrls']['800x800'] ?? $baseInfo['imageUrls']['400x400'] ?? null;

            return new ScrapedProductData(
                title: $title,
                brand: $brand,
                price: $price,
                platform: 'flipkart',
                canonicalUrl: $url,
                imageUrl: $imageUrl,
                rating: null,
                attributes: [
                    'in_stock' => $baseInfo['inStock'] ?? true,
                    'color' => $baseInfo['color'] ?? null,
                ],
                tierUsed: $this->getTierName()
            );
        } catch (\Throwable $e) {
            Log::warning("Flipkart Affiliate extraction failed: " . $e->getMessage());
            throw new RuntimeException("Flipkart Affiliate failed: " . $e->getMessage(), 0, $e);
        }
    }
}
