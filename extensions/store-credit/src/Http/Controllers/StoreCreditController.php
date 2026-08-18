<?php

namespace Extensions\StoreCredit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Extensions\StoreCredit\CreditService;
use Extensions\StoreCredit\Models\CustomerTransaction;
use Illuminate\Http\Request;

class StoreCreditController extends Controller
{
    public function index(Request $request)
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

        return view('store-credit::index', compact('transactions'));
    }

    /**
     * Manual adjustment from the ledger page or the customer panel.
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
