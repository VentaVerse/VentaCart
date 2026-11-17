<?php

namespace Extensions\StoreCredit\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\StoreCredit\CreditService;
use Extensions\StoreCredit\StoreCreditHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

/**
 * The signed-in customer's store credit: the balance and their own ledger.
 * Only their entries are read; the route's customer.auth sees to who they are.
 */
class AccountCreditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $customerId = (int) $request->user('customer')->getKey();
        $balance = CreditService::balance($customerId);

        return view('store-credit::account', [
            'balance' => $balance,
            'history' => StoreCreditHistory::page($customerId, Paginator::resolveCurrentPage(), (int) round($balance * 100)),
        ]);
    }
}
