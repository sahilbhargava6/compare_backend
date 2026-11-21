<?php

namespace Tests\Feature;

use App\DTOs\ScrapedProductData;
use App\Services\AI\AiCurationService;
use App\Services\AI\EmbeddingService;
use App\Services\AI\PineconeService;
use App\Services\ScraperWaterfallService;
use Tests\TestCase;

class AiCurationTest extends TestCase
{
    public function test_ai_curation_adheres_to_strict_prd_schema(): void
    {
        $curator = new AiCurationService();

        $product = new ScrapedProductData(
            title: 'Salicylic Acid 2% Serum',
            brand: 'The Ordinary',
            price: 550.00,
            platform: 'nykaa',
            canonicalUrl: 'https://www.nykaa.com/sample',
            attributes: ['skin_type' => 'oily']
        );

        $userProfile = [
            'skin_type' => 'acne-prone',
            'allergies' => ['fragrance'],
        ];

        $candidates = [
            [
                'id' => 'cand_1',
                'metadata' => [
                    'title' => 'Salicylic Acid + Zinc Serum',
                    'brand' => 'Minimalist',
                    'price' => 499.00,
                    'platform' => 'nykaa',
                ],
            ]
        ];

        $result = $curator->curateAndAnalyze($product, $userProfile, $candidates);

        // Verify Section 5 JSON Schema
        $this->assertArrayHasKey('personalized_analysis', $result);
        $this->assertArrayHasKey('pros', $result['personalized_analysis']);
        $this->assertArrayHasKey('cons', $result['personalized_analysis']);
        $this->assertIsArray($result['personalized_analysis']['pros']);
        $this->assertIsArray($result['personalized_analysis']['cons']);

        $this->assertArrayHasKey('recommended_alternatives', $result);
        $this->assertIsArray($result['recommended_alternatives']);
        $this->assertNotEmpty($result['recommended_alternatives']);
        $this->assertArrayHasKey('product_id', $result['recommended_alternatives'][0]);
        $this->assertArrayHasKey('justification', $result['recommended_alternatives'][0]);
    }

    public function test_embedding_generation_returns_vector_array(): void
    {
        $service = new EmbeddingService();
        $vector = $service->generateEmbedding("Sample Product Niacinamide");

        $this->assertIsArray($vector);
        $this->assertNotEmpty($vector);
        $this->assertIsFloat($vector[0]);
    }

    public function test_scraper_waterfall_falls_back_to_tier3_when_no_credentials(): void
    {
        $waterfall = app(ScraperWaterfallService::class);
        $scraped = $waterfall->scrape('https://www.nykaa.com/sample-product');

        $this->assertInstanceOf(ScrapedProductData::class, $scraped);
        $this->assertEquals('nykaa', $scraped->platform);
        $this->assertStringContainsString('Tier 3', $scraped->tierUsed);
    }
}
