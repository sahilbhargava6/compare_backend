<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'canonical_url',
        'brand',
        'name',
        'platform',
        'vector_id',
    ];

    public function watchlists(): HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(PriceHistory::class)->latestOfMany('recorded_at');
    }
}
