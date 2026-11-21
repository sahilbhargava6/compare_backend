<?php

namespace App\Services;

use App\Contracts\ScraperInterface;
use App\DTOs\ScrapedProductData;
use App\Services\Scrapers\Tier1AffiliateScraper;
use App\Services\Scrapers\Tier2ManagedApiScraper;
use App\Services\Scrapers\Tier3FallbackCrawlerScraper;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ScraperWaterfallService
{
    /**
     * @var ScraperInterface[]
     */
    protected array $tiers;

    public function __construct(
        Tier1AffiliateScraper $tier1,
        Tier2ManagedApiScraper $tier2,
        Tier3FallbackCrawlerScraper $tier3
    ) {
        $this->tiers = [$tier1, $tier2, $tier3];
    }

    /**
     * Execute the Waterfall Strategy across all supported scraper tiers.
     */
    public function scrape(string $url): ScrapedProductData
    {
        $lastException = null;

        foreach ($this->tiers as $scraper) {
            if (!$scraper->supports($url)) {
                continue;
            }

            try {
                Log::info("Attempting extraction with {$scraper->getTierName()} for URL: {$url}");
                return $scraper->extract($url);
            } catch (Throwable $e) {
                Log::warning("{$scraper->getTierName()} failed for URL {$url}: " . $e->getMessage());
                $lastException = $e;
            }
        }

        throw new RuntimeException(
            "All scraper tiers failed for URL: {$url}. Last error: " . ($lastException ? $lastException->getMessage() : 'None'),
            previous: $lastException
        );
    }
}
