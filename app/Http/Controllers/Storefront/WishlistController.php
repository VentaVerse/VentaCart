<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\WishlistItemRequest;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;

/**
 * The wishlist page and the endpoints a theme's heart buttons call. Every
 * answer carries the new state, so a button and the header count update
 * from one response.
 */
class WishlistController extends Controller
{
    public function __construct(private WishlistService $wishlist)
    {
    }

    public function index()
    {
        return view('wishlist.index', ['products' => $this->wishlist->products()]);
    }

    public function state(): JsonResponse
    {
        return response()->json(['ids' => $this->wishlist->ids(), 'count' => $this->wishlist->count()]);
    }

    public function add(WishlistItemRequest $request): JsonResponse
    {
        $id = (int) $request->validated()['product_id'];
        $this->wishlist->add($id);

        return response()->json(['in' => $this->wishlist->has($id), 'count' => $this->wishlist->count()]);
    }

    public function remove(int $product): JsonResponse
    {
        $this->wishlist->remove($product);

        return response()->json(['in' => false, 'count' => $this->wishlist->count()]);
    }
}
