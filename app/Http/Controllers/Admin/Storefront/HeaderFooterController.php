<?php

namespace App\Http\Controllers\Admin\Storefront;

use App\Http\Controllers\Admin\Layout\MenuController;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EditTheme;
use App\Models\FooterColumn;
use App\Models\Setting;
use App\Services\ThemeService;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront > Header & footer: what sits on every page. The announcement
 * bar, the top bar links, the main menu and the footer columns, each its
 * own tab with its own save. A live theme that keeps its own storage edits
 * these in its own editor, so the page hands over to it.
 */
class HeaderFooterController extends Controller
{
    public const TABS = ['announcement' => 'Announcement', 'top-bar' => 'Top bar', 'main-menu' => 'Main menu', 'footer' => 'Footer'];

    public function show(Request $request, ThemeService $theme, MenuController $menus): Response
    {
        if ($away = $this->ownEditor($theme)) {
            return $away;
        }

        $tab = (string) $request->query('tab', 'announcement');
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'announcement';

        return response()->view('admin.storefront.header-footer', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'header' => Setting::where('group', 'header')->pluck('value', 'key')->all(),
            'footerColumns' => $tab === 'footer' ? FooterColumn::orderBy('sort_order')->get() : collect(),
        ] + ($tab === 'main-menu' ? $menus->screen() : []));
    }

    public function updateAnnouncement(Request $request, ThemeService $theme): RedirectResponse|Response
    {
        if ($away = $this->ownEditor($theme)) {
            return $away;
        }

        $validated = $request->validate([
            'announcement_enabled' => ['nullable', 'boolean'],
            'announcement_text' => ['nullable', 'string', 'max:255'],
            'announcement_link' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                if (trim((string) $value) !== '' && SafeInput::link($value) === '') {
                    $fail('The link must be a path such as /sale, or an http or https address.');
                }
            }],
        ]);

        $this->save([
            'announcement_enabled' => $request->boolean('announcement_enabled') ? '1' : '0',
            'announcement_text' => (string) ($validated['announcement_text'] ?? ''),
            'announcement_link' => SafeInput::link($validated['announcement_link'] ?? ''),
        ], $theme);

        return redirect()->route('admin.header-footer', ['tab' => 'announcement'])->with('success', 'Announcement saved.');
    }

    public function updateTopBar(Request $request, ThemeService $theme): RedirectResponse|Response
    {
        if ($away = $this->ownEditor($theme)) {
            return $away;
        }

        $validated = $request->validate([
            'topbar_links' => ['nullable', 'json', 'max:5000'],
        ]);

        // A link is kept only with a label and an address that is a path or
        // a web address: a javascript: address would run in shoppers' browsers.
        $links = collect(json_decode((string) ($validated['topbar_links'] ?? '[]'), true) ?: [])
            ->filter(fn ($link) => is_array($link))
            ->map(fn (array $link) => [
                'label' => mb_substr(trim((string) ($link['label'] ?? '')), 0, 100),
                'url' => SafeInput::link(mb_substr(trim((string) ($link['url'] ?? '')), 0, 500)),
            ])
            ->filter(fn (array $link) => $link['label'] !== '' && $link['url'] !== '')
            ->values()
            ->all();

        $this->save(['topbar_links' => json_encode($links)], $theme);

        return redirect()->route('admin.header-footer', ['tab' => 'top-bar'])->with('success', 'Top bar saved.');
    }

    /** @param array<string, string> $values */
    private function save(array $values, ThemeService $theme): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['group' => 'header', 'key' => $key], ['value' => $value]);
        }
        $theme->clearCache();
    }

    /** The live theme's own editor, when it keeps its own storage. */
    private function ownEditor(ThemeService $theme): ?Response
    {
        $live = $theme->getActiveTheme();

        return $theme->keepsOwnStorage($live) ? EditTheme::sendAway($theme, $live) : null;
    }
}
