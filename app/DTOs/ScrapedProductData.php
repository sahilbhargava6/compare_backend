<?php

namespace App\DTOs;

readonly class ScrapedProductData
{
    public function __construct(
        public string $title,
        public string $brand,
        public float $price,
        public string $platform,
        public string $canonicalUrl,
        public ?string $imageUrl = null,
        public ?float $rating = null,
        public array $attributes = [],
        public ?string $tierUsed = null,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'brand' => $this->brand,
            'price' => $this->price,
            'platform' => $this->platform,
            'canonical_url' => $this->canonicalUrl,
            'image_url' => $this->imageUrl,
            'rating' => $this->rating,
            'attributes' => $this->attributes,
            'tier_used' => $this->tierUsed,
        ];
    }
}
