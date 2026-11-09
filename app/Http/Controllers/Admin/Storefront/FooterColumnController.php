<?php

namespace App\Http\Controllers\Admin\Storefront;

use App\Http\Controllers\Controller;
use App\Models\FooterColumn;
use App\Services\ThemeService;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The footer columns, edited on Storefront > Header & footer: links,
 * payment icons, social links and free text. Store content every theme
 * without an editor of its own draws.
 */
class FooterColumnController extends Controller
{
    private const TYPES = ['links', 'payment_icons', 'social', 'text'];

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:' . implode(',', self::TYPES)],
        ]);

        $column = FooterColumn::create([
            'title' => $request->input('title'),
            'type' => $request->input('type'),
            'config' => $request->input('type') === 'text' ? ['content' => ''] : ['items' => []],
            'sort_order' => (FooterColumn::max('sort_order') ?? 0) + 1,
        ]);

        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.footer.edit', $column)->with('success', 'Footer column added. Fill it in below.');
    }

    public function edit(FooterColumn $column): View
    {
        return view('admin.storefront.footer-column', compact('column'));
    }

    public function update(Request $request, FooterColumn $column): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'config.content' => ['nullable', 'string', 'max:5000'],
            // A file in public storage is served as it is: images only.
            'icon_uploads.*' => ['nullable', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:1024'],
        ]);

        $input = (array) $request->input('config', []);
        $config = match ($column->type) {
            'links', 'social' => ['links' => $this->links($input['links'] ?? [])],
            'payment_icons' => ['icons' => $this->icons($input['icons'] ?? [], $request->file('icon_uploads', []))],
            'text' => ['content' => (string) ($input['content'] ?? '')],
            default => $column->config,
        };

        $column->update([
            'title' => $request->input('title'),
            'config' => $config,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.header-footer', ['tab' => 'footer'])->with('success', 'Footer column updated.');
    }

    public function destroy(FooterColumn $column): RedirectResponse
    {
        $column->delete();
        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.header-footer', ['tab' => 'footer'])->with('success', 'Footer column deleted.');
    }

    /** The columns' order, saved by the page's Save. */
    public function arrange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:footer_columns,id'],
        ]);

        foreach (array_values($validated['order']) as $position => $id) {
            FooterColumn::whereKey($id)->update(['sort_order' => $position]);
        }

        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.header-footer', ['tab' => 'footer'])->with('success', 'Footer order saved.');
    }

    /** Links whose address is a path or a web address; the rest are dropped. */
    private function links(mixed $links): array
    {
        return collect(is_array($links) ? $links : [])
            ->filter(fn ($link) => is_array($link))
            ->map(fn (array $link) => array_filter([
                'label' => mb_substr(trim((string) ($link['label'] ?? '')), 0, 100),
                'url' => SafeInput::link($link['url'] ?? ''),
                'platform' => isset($link['platform']) ? mb_substr((string) $link['platform'], 0, 30) : null,
            ], fn ($value) => $value !== null))
            ->filter(fn (array $link) => $link['url'] !== '')
            ->values()
            ->all();
    }

    private function icons(mixed $icons, array $uploads): array
    {
        $out = [];
        foreach (is_array($icons) ? $icons : [] as $index => $icon) {
            $icon = is_string($icon) ? ['type' => 'text', 'value' => $icon] : (is_array($icon) ? $icon : []);
            if (isset($uploads[$index])) {
                $icon = ['type' => 'image', 'value' => $uploads[$index]->store('payment-icons', 'public')];
            }
            $value = trim((string) ($icon['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $type = ($icon['type'] ?? 'text') === 'image' ? 'image' : 'text';
            // A stored image is a path in public storage, never a traversal.
            if ($type === 'image' && SafeInput::storagePath($value) === '') {
                continue;
            }
            $out[] = ['type' => $type, 'value' => mb_substr($value, 0, 255)];
        }

        return $out;
    }
}
