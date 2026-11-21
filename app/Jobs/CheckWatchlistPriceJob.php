<?php

namespace App\Jobs;

use App\Models\PriceHistory;
use App\Models\Watchlist;
use App\Services\ScraperWaterfallService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckWatchlistPriceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $watchlistId
    ) {}

    public function handle(ScraperWaterfallService $waterfallService): void
    {
        $watchlist = Watchlist::with(['product', 'user'])->find($this->watchlistId);

        if (!$watchlist || !$watchlist->is_active || !$watchlist->product) {
            return;
        }

        $product = $watchlist->product;
        Log::info("Scheduled price check for Watchlist ID: {$watchlist->id} (Product: {$product->name})");

        try {
            $scraped = $waterfallService->scrape($product->canonical_url);
            $newPrice = $scraped->price;

            // Record latest price in price history
            PriceHistory::create([
                'product_id' => $product->id,
                'price' => $newPrice,
                'recorded_at' => now(),
            ]);

            // Check if price dropped below target price
            if ($newPrice <= (float) $watchlist->target_price) {
                Log::alert("PRICE DROP ALERT! Product {$product->name} reached ₹{$newPrice} (Target: ₹{$watchlist->target_price}) for User: {$watchlist->user_id}");
                // In production, dispatch notification via mail, web push, or SMS
            }
        } catch (\Throwable $e) {
            Log::error("Failed to check price for Watchlist item {$watchlist->id}: " . $e->getMessage());
        }
    }
}
