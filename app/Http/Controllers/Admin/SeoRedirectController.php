<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeoRedirectController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $redirects = Redirect::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('source_path', 'like', "%{$search}%")
                        ->orWhere('destination_path', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('hits')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $prefill = [
            'source_path' => $request->query('source_path', ''),
            'destination_path' => $request->query('destination_path', ''),
        ];

        return view('admin.extensions.seo.redirects', compact('redirects', 'search', 'prefill'));
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);
        Redirect::create($data);

        return redirect()->route('admin.extensions.seo.redirects.index')
            ->with('success', 'Redirect created.');
    }

    public function update(Request $request, Redirect $redirect)
    {
        $data = $this->validateRow($request, $redirect->id);
        $redirect->update($data);

        return redirect()->route('admin.extensions.seo.redirects.index')
            ->with('success', 'Redirect updated.');
    }

    public function destroy(Redirect $redirect)
    {
        $redirect->delete();

        return redirect()->route('admin.extensions.seo.redirects.index')
            ->with('success', 'Redirect deleted.');
    }

    private function validateRow(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'source_path' => [
                'required', 'string', 'max:255', 'regex:#^/#',
                Rule::unique('redirects', 'source_path')->ignore($ignoreId),
            ],
            'destination_path' => ['required_unless:status_code,410', 'nullable', 'string', 'max:255'],
            'status_code' => ['required', 'integer', 'in:301,302,410'],
            'preserve_query' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];

        $validated = $request->validate($rules, [
            'source_path.regex' => 'Source path must start with a forward slash, e.g. /old-page.',
            'destination_path.required_unless' => 'Destination is required unless status code is 410 (Gone).',
        ]);

        $validated['preserve_query'] = $request->boolean('preserve_query');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['destination_path'] = $validated['destination_path'] ?? '';

        return $validated;
    }
}
