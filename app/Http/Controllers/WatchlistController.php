<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    /**
     * List user watchlist items with current product and price information.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');

        $query = Watchlist::with(['product.latestPrice']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'count' => $items->count(),
            'watchlists' => $items,
        ]);
    }

    /**
     * Add product to user watchlist with a target price.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
            'product_id' => ['required', 'string', 'exists:products,id'],
            'target_price' => ['required', 'numeric', 'min:0'],
        ]);

        $watchlist = Watchlist::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'product_id' => $validated['product_id'],
            ],
            [
                'target_price' => $validated['target_price'],
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Item added to watchlist successfully.',
            'watchlist' => $watchlist->load('product'),
        ], 201);
    }

    /**
     * Update target price or active status for a watchlist item.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $watchlist = Watchlist::findOrFail($id);

        $validated = $request->validate([
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $watchlist->update(array_filter($validated, fn($value) => !is_null($value)));

        return response()->json([
            'message' => 'Watchlist item updated successfully.',
            'watchlist' => $watchlist,
        ]);
    }

    /**
     * Remove item from watchlist.
     */
    public function destroy(string $id): JsonResponse
    {
        $watchlist = Watchlist::findOrFail($id);
        $watchlist->delete();

        return response()->json([
            'message' => 'Watchlist item removed successfully.',
        ]);
    }
}
