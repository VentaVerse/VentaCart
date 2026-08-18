<?php

namespace Extensions\StoreCredit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Setting;
use Extensions\StoreCredit\CreditService;
use Extensions\StoreCredit\Models\CustomerTransaction;
use Illuminate\Http\Request;

class StoreCreditController extends Controller
{
    /**
     * The provider-registered default (StoreCreditServiceProvider): after
     * coupons, so credit covers the coupon-discounted remainder.
     */
    private const DEFAULT_SORT_ORDER = 700;

    /**
     * Configure lands here: the OC-style thin page — the enable switch
     * (page header) and the checkout sort order. Saved through the
     * shared settings endpoint (group 'totals').
     */
    public function settings()
    {
        return view('store-credit::settings', [
            'sortOrder' => (int) (Setting::where('group', 'totals')->where('key', 'store_credit_sort_order')->value('value') ?? self::DEFAULT_SORT_ORDER),
        ]);
    }

    public function ledger(Request $request)
    {
        $query = CustomerTransaction::with(['customer', 'creator'])->latest('created_at')->latest('id');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $transactions = $query->paginate(30)->withQueryString();

        return view('store-credit::ledger', compact('transactions'));
    }

    /**
     * Manual adjustment from the customer panel (or the ledger page).
     * Signed: positive grants, negative deducts (a hand re-credit after
     * a cancelled order is just a positive entry).
     */
    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-999999.99', 'max:999999.99'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        CreditService::add(
            (int) $validated['customer_id'],
            (float) $validated['amount'],
            $validated['description'],
            null,
            $request->user()->id,
        );

        $customer = Customer::find($validated['customer_id']);

        return back()->with('success', 'Credit adjusted for ' . $customer->email . '. New balance: ' . number_format(CreditService::balance($customer->id), 2) . '.');
    }
}
