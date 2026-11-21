<?php

namespace App\Services\AI;

use App\DTOs\ScrapedProductData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCurationService
{
    /**
     * Analyze a product against user preferences and re-rank candidate alternatives.
     * Enforces strict JSON Schema from Section 5 of the PRD.
     *
     * @param ScrapedProductData|array $product
     * @param array $userPreferences E.g. ['skin_type' => 'acne-prone', 'allergies' => ['fragrance']]
     * @param array $candidates Candidate alternative items from Pinecone/DB
     * @return array
     */
    public function curateAndAnalyze(
        ScrapedProductData|array $product,
        array $userPreferences = [],
        array $candidates = []
    ): array {
        $productArray = $product instanceof ScrapedProductData ? $product->toArray() : $product;
        $provider = config('services.ai.provider', 'openai');

        if ($provider === 'gemini') {
            $analysis = $this->curateWithGemini($productArray, $userPreferences, $candidates);
        } else {
            $analysis = $this->curateWithOpenAi($productArray, $userPreferences, $candidates);
        }

        return $analysis ?? $this->fallbackHeuristicAnalysis($productArray, $userPreferences, $candidates);
    }

    protected function getSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert AI product curator and dermatologist/cosmetic analyst.
Evaluate the given product and candidate alternatives strictly against the user's personal profile (skin type, allergies, sensitivities, target budget).
You MUST respond with pure JSON adhering strictly to this schema:
{
  "personalized_analysis": {
    "pros": ["string"],
    "cons": ["string"]
  },
  "recommended_alternatives": [
    {
      "product_id": "string",
      "justification": "string"
    }
  ]
}
Rules:
1. Markdown backticks (e.g. ```json) are STRICTLY FORBIDDEN. Output raw JSON only.
2. Select up to 3 best alternatives from the candidates list that fit within the user's budget and skin criteria.
3. Every pro and con must be personalized according to the user's profile.
PROMPT;
    }

    protected function buildUserPayload(array $product, array $userPreferences, array $candidates): string
    {
        return json_encode([
            'target_product' => [
                'title' => $product['title'] ?? 'Product',
                'brand' => $product['brand'] ?? 'Brand',
                'price' => $product['price'] ?? 0,
                'platform' => $product['platform'] ?? 'e-commerce',
                'attributes' => $product['attributes'] ?? [],
            ],
            'user_profile' => $userPreferences,
            'candidate_alternatives' => array_map(function ($item) {
                return [
                    'product_id' => (string) ($item['id'] ?? ''),
                    'title' => $item['metadata']['title'] ?? ($item['name'] ?? ''),
                    'brand' => $item['metadata']['brand'] ?? ($item['brand'] ?? ''),
                    'price' => $item['metadata']['price'] ?? ($item['price'] ?? 0),
                    'platform' => $item['metadata']['platform'] ?? ($item['platform'] ?? ''),
                ];
            }, $candidates),
        ], JSON_PRETTY_PRINT);
    }

    protected function curateWithOpenAi(array $product, array $userPreferences, array $candidates): ?array
    {
        $apiKey = config('services.ai.openai.api_key');
        $model = config('services.ai.openai.model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->getSystemPrompt()],
                        ['role' => 'user', 'content' => $this->buildUserPayload($product, $userPreferences, $candidates)],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['personalized_analysis'])) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("OpenAI Curation failed: " . $e->getMessage());
        }

        return null;
    }

    protected function curateWithGemini(array $product, array $userPreferences, array $candidates): ?array
    {
        $apiKey = config('services.ai.gemini.api_key');
        $model = config('services.ai.gemini.model', 'gemini-1.5-flash');

        if (empty($apiKey)) {
            return null;
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::timeout(15)->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $this->getSystemPrompt()]]
                ],
                'contents' => [
                    ['parts' => [['text' => $this->buildUserPayload($product, $userPreferences, $candidates)]]]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.2,
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json('candidates.0.content.parts.0.text');
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['personalized_analysis'])) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Gemini Curation failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Resilient rule-based fallback adhering strictly to Section 5 schema.
     */
    protected function fallbackHeuristicAnalysis(array $product, array $userPreferences, array $candidates): array
    {
        $skinType = $userPreferences['skin_type'] ?? 'all skin types';
        $allergies = $userPreferences['allergies'] ?? [];
        $title = $product['title'] ?? 'Selected Product';
        $brand = $product['brand'] ?? 'Brand';

        $pros = [
            "Well rated formulation suitable for {$skinType}.",
            "Competitive pricing on " . ucfirst($product['platform'] ?? 'e-commerce') . " with active tracking.",
        ];

        $cons = [];
        if (!empty($allergies)) {
            $cons[] = "Check ingredient list if you are sensitive to " . implode(', ', (array) $allergies) . ".";
        } else {
            $cons[] = "May require patch testing if switching formulations.";
        }

        $recommended = [];
        foreach (array_slice($candidates, 0, 3) as $candidate) {
            $candidateTitle = $candidate['metadata']['title'] ?? ($candidate['name'] ?? 'Alternative');
            $candidateBrand = $candidate['metadata']['brand'] ?? ($candidate['brand'] ?? 'Brand');
            $candidatePrice = $candidate['metadata']['price'] ?? ($candidate['price'] ?? 0);

            $recommended[] = [
                'product_id' => (string) ($candidate['id'] ?? uniqid('prod_')),
                'justification' => "Great alternative by {$candidateBrand} ({$candidateTitle}) priced at ₹{$candidatePrice}, optimized for {$skinType}.",
            ];
        }

        return [
            'personalized_analysis' => [
                'pros' => $pros,
                'cons' => $cons,
            ],
            'recommended_alternatives' => $recommended,
        ];
    }
}
