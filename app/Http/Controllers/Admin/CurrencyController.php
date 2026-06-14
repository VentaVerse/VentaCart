<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\CurrencyService;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'rate' => 'required|numeric|min:0.000001',
        ]);

        Currency::create([
            'code' => strtoupper($request->input('code')),
            'name' => $request->input('name'),
            'symbol' => $request->input('symbol'),
            'rate' => $request->input('rate'),
            'is_default' => false,
            'is_enabled' => true,
        ]);

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Currency added.')->with('tab', 'currency');
    }

    public function update(Request $request, Currency $currency)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'rate' => 'required|numeric|min:0.000001',
        ]);

        $currency->update([
            'name' => $request->input('name'),
            'symbol' => $request->input('symbol'),
            'rate' => $request->input('rate'),
            // The default currency is the base for every price and conversion, so
            // it can never be disabled — force it on regardless of the request.
            'is_enabled' => $currency->is_default ? true : $request->boolean('is_enabled'),
        ]);

        if ($request->boolean('is_default') && !$currency->is_default) {
            // Recalculate all rates relative to new default
            $oldRate = $currency->rate; // rate of new default relative to old default
            if ($oldRate > 0) {
                $others = Currency::where('id', '!=', $currency->id)->get();
                foreach ($others as $other) {
                    $other->update(['rate' => $other->rate / $oldRate]);
                }
            }
            Currency::where('id', '!=', $currency->id)->update(['is_default' => false]);
            $currency->update(['is_default' => true, 'rate' => 1.000000]);
        }

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Currency updated.')->with('tab', 'currency');
    }

    public function destroy(Currency $currency)
    {
        if ($currency->is_default) {
            return back()->with('error', 'Cannot delete the default currency.')->with('tab', 'currency');
        }

        $currency->delete();
        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Currency deleted.')->with('tab', 'currency');
    }

    public function fetchRates(CurrencyService $currencyService)
    {
        $result = $currencyService->updateRates();

        if ($result['success']) {
            app(ThemeService::class)->clearCache();
            return back()->with('success', 'Rates updated: ' . $result['updated'] . ' currencies.')->with('tab', 'currency');
        }

        return back()->with('error', 'Failed to fetch rates: ' . $result['error'])->with('tab', 'currency');
    }
}
