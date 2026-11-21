<?php

namespace App\Console\Commands;

use App\Jobs\CheckWatchlistPriceJob;
use App\Models\Watchlist;
use Illuminate\Console\Command;

class CheckWatchlistPricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchlist:check-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch active watchlist items and dispatch asynchronous price checking queue jobs every 6 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Batching active watchlist URLs for price verification...');

        $count = 0;
        Watchlist::where('is_active', true)
            ->whereHas('product')
            ->chunk(50, function ($watchlists) use (&$count) {
                foreach ($watchlists as $watchlist) {
                    CheckWatchlistPriceJob::dispatch((string) $watchlist->id);
                    $count++;
                }
            });

        $this->info("Successfully dispatched {$count} price check jobs to the queue.");

        return Command::SUCCESS;
    }
}
