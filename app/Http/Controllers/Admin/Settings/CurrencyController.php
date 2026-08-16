<?php

namespace App\Http\Controllers\Admin\Settings;

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
            // The default currency is the conversion base, so its rate is always 1
            // and it can never be disabled — force both regardless of the request.
            'rate' => $currency->is_default ? 1.000000 : $request->input('rate'),
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

    /**
     * One save for the whole table (the doctrine). The default
     * currency keeps rate 1 and stays enabled regardless of input;
     * changing WHICH currency is default remains its own action
     * because it recalculates every rate.
     */
    public function updateAll(Request $request)
    {
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.symbol' => ['required', 'string', 'max:10'],
            'rows.*.rate' => ['required', 'numeric', 'min:0.000001'],
            'rows.*.is_enabled' => ['boolean'],
        ]);

        $currencies = Currency::whereIn('id', array_keys($validated['rows']))->get()->keyBy('id');

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $currencies) {
            foreach ($validated['rows'] as $id => $row) {
                $currency = $currencies->get((int) $id);
                if (! $currency) {
                    continue;
                }
                $currency->update([
                    'name' => $row['name'],
                    'symbol' => $row['symbol'],
                    'rate' => $currency->is_default ? 1.000000 : $row['rate'],
                    'is_enabled' => $currency->is_default ? true : filter_var($row['is_enabled'] ?? false, FILTER_VALIDATE_BOOL),
                ]);
            }
        });

        app(ThemeService::class)->clearCache();

        return back()->with('success', 'Currencies saved.');
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
