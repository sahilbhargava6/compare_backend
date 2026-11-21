<?php

namespace App\Contracts;

use App\DTOs\ScrapedProductData;

interface ScraperInterface
{
    /**
     * Get the name/identifier of the scraper tier.
     */
    public function getTierName(): string;

    /**
     * Check if this scraper tier can handle the given URL.
     */
    public function supports(string $url): bool;

    /**
     * Extract product data from the specified target URL.
     * 
     * @throws \RuntimeException if extraction fails
     */
    public function extract(string $url): ScrapedProductData;
}
