<?php

namespace Extensions\ErpSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Support\ApiAbility;
use Illuminate\Http\Request;

class ErpSyncSettingsController extends Controller
{
    public function page()
    {
        return view('erp-sync::settings', [
            'keys' => ApiKey::orderBy('label')->get(),
            'areas' => ApiAbility::AREAS,
            'modes' => ApiAbility::MODES,
        ]);
    }

    /**
     * Mint a key and show it once.
     *
     * The secret is flashed rather than stored, because only its hash is kept:
     * there is no second chance to read it and the screen has to say so.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'abilities' => ['sometimes', 'array'],
        ]);

        $abilities = ApiKey::clean((array) ($data['abilities'] ?? []));

        // A key permitted nothing can do nothing, and would look like a
        // working key in the list. Refuse it rather than issue a decoration.
        if ($abilities === []) {
            return back()->with('error', 'Choose at least one permission for this key.');
        }

        [$key, $secret] = ApiKey::issue($data['label'], $abilities);

        return back()
            ->with('success', 'API key created. Copy it now, it will not be shown again.')
            ->with('new_api_key', $secret)
            ->with('new_api_key_label', $key->label);
    }

    public function destroy(ApiKey $apiKey)
    {
        $label = $apiKey->label;
        $apiKey->delete();

        return back()->with('success', "API key \"{$label}\" deleted. Anything using it will stop working immediately.");
    }
}
