<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    /**
     * Generate vector embedding for product text attributes.
     *
     * @return array<float>
     */
    public function generateEmbedding(string $text): array
    {
        $provider = config('services.ai.provider', 'openai');

        if ($provider === 'gemini') {
            return $this->generateGeminiEmbedding($text);
        }

        return $this->generateOpenAiEmbedding($text);
    }

    protected function generateOpenAiEmbedding(string $text): array
    {
        $apiKey = config('services.ai.openai.api_key');
        $model = config('services.ai.openai.embedding_model', 'text-embedding-3-small');

        if (empty($apiKey)) {
            Log::info("OpenAI API key missing; generating deterministic mock embedding.");
            return $this->generateMockEmbedding($text);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $model,
                    'input' => $text,
                ]);

            if ($response->successful()) {
                $embedding = $response->json('data.0.embedding');
                if (is_array($embedding)) {
                    return $embedding;
                }
            }
            Log::warning("OpenAI Embedding API error: " . $response->body());
        } catch (\Throwable $e) {
            Log::warning("OpenAI Embedding generation failed: " . $e->getMessage());
        }

        return $this->generateMockEmbedding($text);
    }

    protected function generateGeminiEmbedding(string $text): array
    {
        $apiKey = config('services.ai.gemini.api_key');
        $model = config('services.ai.gemini.embedding_model', 'text-embedding-004');

        if (empty($apiKey)) {
            Log::info("Gemini API key missing; generating deterministic mock embedding.");
            return $this->generateMockEmbedding($text);
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent?key={$apiKey}";
            $response = Http::timeout(10)->post($url, [
                'model' => "models/{$model}",
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $values = $response->json('embedding.values');
                if (is_array($values)) {
                    return $values;
                }
            }
            Log::warning("Gemini Embedding API error: " . $response->body());
        } catch (\Throwable $e) {
            Log::warning("Gemini Embedding generation failed: " . $e->getMessage());
        }

        return $this->generateMockEmbedding($text);
    }

    /**
     * Fallback mock embedding with 1536 dimensions for testing.
     */
    protected function generateMockEmbedding(string $text): array
    {
        $hash = md5($text);
        $vector = [];
        for ($i = 0; $i < 1536; $i++) {
            $val = hexdec(substr($hash, ($i % 30), 2));
            $vector[] = (float) number_format(($val - 128) / 128, 6);
        }
        return $vector;
    }
}
