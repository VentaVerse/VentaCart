<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Http;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Support\CartGroups;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The checkout page, drawn by the extension: the cart's lines under their
 * groups. Placing the order still posts to core's checkout.place.
 */
class SampleCheckoutController extends Controller
{
    public function __invoke(CartService $cart): View|RedirectResponse
    {
        $items = $cart->getItems();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('sample-extension::checkout', [
            'items' => $items->keyBy('id'),
            'groups' => CartGroups::for($items) ?? [['key' => 'other', 'label' => '', 'url' => null, 'lines' => $items->pluck('id')->all(), 'shipping' => null]],
        ]);
    }
}
