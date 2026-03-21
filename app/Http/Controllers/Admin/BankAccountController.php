<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BankAccountController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'qr_image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($request->hasFile('qr_image')) {
            $validated['qr_image'] = $request->file('qr_image')->store('bank-qr', 'public');
        } else {
            unset($validated['qr_image']);
        }

        BankAccount::create($validated);

        return redirect()->route('admin.payment-settings.index', ['tab' => 'bank-transfer'])
            ->with('success', 'Bank account added.');
    }

    public function edit(BankAccount $bankAccount)
    {
        $bankAccounts = BankAccount::orderBy('sort_order')->get();

        return view('admin.payment-settings.edit-bank-account', compact('bankAccount', 'bankAccounts'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'qr_image' => ['nullable', 'image', 'max:2048'],
            'remove_qr' => ['nullable'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Handle QR image
        if ($request->boolean('remove_qr') && $bankAccount->qr_image) {
            Storage::disk('public')->delete($bankAccount->qr_image);
            $validated['qr_image'] = null;
        } elseif ($request->hasFile('qr_image')) {
            if ($bankAccount->qr_image) {
                Storage::disk('public')->delete($bankAccount->qr_image);
            }
            $validated['qr_image'] = $request->file('qr_image')->store('bank-qr', 'public');
        } else {
            unset($validated['qr_image']);
        }

        unset($validated['remove_qr']);
        $bankAccount->update($validated);

        return redirect()->route('admin.payment-settings.index', ['tab' => 'bank-transfer'])
            ->with('success', 'Bank account updated.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        if ($bankAccount->qr_image) {
            Storage::disk('public')->delete($bankAccount->qr_image);
        }

        $bankAccount->delete();

        return redirect()->route('admin.payment-settings.index', ['tab' => 'bank-transfer'])
            ->with('success', 'Bank account removed.');
    }
}
