<?php

namespace App\Events;

use App\DTOs\ScrapedProductData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductIngestedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $userId,
        public ScrapedProductData $productData,
        public ?string $productId = null,
        public array $curation = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'product.ingested';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'product' => $this->productData->toArray(),
            'curation' => $this->curation,
        ];
    }
}
