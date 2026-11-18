<?php

namespace Themes\Xelga\Rendering;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\Preview\PreviewMode;
use Themes\Xelga\Preview\PreviewSession;

/**
 * What the theme's views call: draw the page's template, draw the header
 * and footer groups, and name the tokens stylesheet. One per request.
 */
final class Storefront
{
    private string $pageType = 'page';

    private string $handle = 'page';

    private mixed $subject = null;

    /** @var array<string, mixed> */
    private array $page = [];

    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly SectionRenderer $renderer,
        private readonly TemplateResolver $resolver,
    ) {
    }

    /**
     * The page's own sections, from its template.
     *
     * @param  array<string, mixed>  $page  the view data core's controller passed to the page
     */
    public function template(string $pageType, mixed $subject = null, array $page = []): HtmlString
    {
        $this->pageType = $pageType;
        $this->subject = $subject;
        $this->page = $page;
        $this->handle = $this->resolver->handleFor($pageType, $subject);

        $content = self::withLockedSections($this->documents->content($this->handle), $this->handle);

        // Rows and sides decide the reading order. A failure here must not
        // cost the shopper the page: it is reported and the page is drawn
        // in its stored order, one full-width section after another.
        try {
            $content = Layout::normalize($content, app(LayoutRules::class, ['handle' => $this->handle]))['content'];
        } catch (\Throwable $e) {
            report($e);
            unset($content['rows'], $content['sides']);
        }

        return new HtmlString($this->renderer->render(
            $content,
            new RenderContext($pageType, $this->handle, 'template', $this->preview(), $subject, $page),
        ));
    }

    /**
     * The document with the sections it always holds (see
     * Defaults::lockedState()). A template always draws its page's main
     * section (the product, the listing, the cart), and the header and the
     * footer always hold the Header and the Footer. The editor cannot remove
     * them, but a stored document that lost one is given the shipped one
     * back in its usual place. A main section is never hidden, so a hidden
     * one is shown. A fixed section may be hidden: one put back comes back
     * hidden, so the store keeps the look it had, and a hidden one stays
     * hidden.
     */
    public static function withLockedSections(array $content, string $handle): array
    {
        $kind = Handles::kind($handle);
        if ($kind !== 'template' && $kind !== 'group') {
            return $content;
        }

        $sections = [];
        foreach ((array) ($content['sections'] ?? []) as $id => $section) {
            $sections[(string) $id] = (array) $section;
        }
        $state = Defaults::lockedState($handle, $sections);
        if ($state['missing'] === [] && $state['hidden_main'] === []) {
            return $content;
        }

        foreach ($state['hidden_main'] as $id) {
            $sections[$id]['disabled'] = false;
        }

        $order = array_values(array_filter((array) ($content['order'] ?? []), 'is_string'));
        $defaults = Defaults::content($kind === 'group' ? $handle : Handles::baseFor((string) Handles::pageType($handle)));
        $defaultOrder = array_values(array_filter((array) ($defaults['order'] ?? []), 'is_string'));
        foreach ($state['missing'] as $type) {
            foreach ($defaultOrder as $index => $id) {
                $section = $defaults['sections'][$id] ?? null;
                if (! is_array($section) || ($section['type'] ?? null) !== $type) {
                    continue;
                }
                $schema = Catalog::section($type);
                if ($schema !== null && $schema->fixed()) {
                    $section['disabled'] = true;
                }
                $newId = $id;
                for ($n = 2; isset($sections[$newId]); $n++) {
                    $newId = $id . '-' . $n;
                }
                array_splice($order, self::placeFor($order, $defaultOrder, $index), 0, [$newId]);
                $sections[$newId] = $section;
                break;
            }
        }

        $content['sections'] = $sections;
        $content['order'] = $order;

        return $content;
    }

    /**
     * Where a section the shipped document has at $index goes in $order:
     * right after the nearest section before it in the shipped order that
     * the document still holds, else at its shipped place.
     *
     * @param  list<string>  $order
     * @param  list<string>  $defaultOrder
     */
    private static function placeFor(array $order, array $defaultOrder, int $index): int
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            $at = array_search($defaultOrder[$j], $order, true);
            if ($at !== false) {
                return $at + 1;
            }
        }

        return min($index, count($order));
    }

    /**
     * How wide the page's content area is drawn, in twelfths of the page:
     * 12 for a page without sides, or one drawn without a template.
     */
    public function contentSpan(): int
    {
        return $this->renderer->contentSpan();
    }

    /**
     * Which sides the page's template drew beside its content: 'left',
     * 'right', 'both', or '' for a page without sides.
     */
    public function sides(): string
    {
        $left = $this->renderer->sideHtml('left') !== '';
        $right = $this->renderer->sideHtml('right') !== '';

        return match (true) {
            $left && $right => 'both',
            $left => 'left',
            $right => 'right',
            default => '',
        };
    }

    /**
     * The sections of one side the page's template drew.
     *
     * @param  'left'|'right'  $which
     */
    public function side(string $which): HtmlString
    {
        return new HtmlString($this->renderer->sideHtml($which));
    }

    /** Remember which page this is, for a page drawn without a template. */
    public function page(string $pageType): void
    {
        $this->pageType = $pageType;
        $this->handle = $this->resolver->handleFor($pageType);
    }

    /** The header or footer group. */
    public function group(string $handle): HtmlString
    {
        if (! in_array($handle, Handles::GROUPS, true)) {
            return new HtmlString('');
        }

        return new HtmlString($this->renderer->render(
            self::withLockedSections($this->documents->content($handle), $handle),
            new RenderContext($this->pageType, $this->handle, $handle, $this->preview(), $this->subject, $this->page),
        ));
    }

    public function preview(): bool
    {
        return PreviewMode::active();
    }

    /** The tokens stylesheet: versioned and cached for shoppers, the draft in the preview. */
    public function tokensUrl(): string
    {
        if ($this->preview()) {
            return route('xelga.tokens.draft', ['n' => PreviewSession::nonce()], false);
        }

        return route('xelga.tokens', ['version' => $this->documents->settingsVersion()], false);
    }

    /** The heading font file to preload, when the build knows it. */
    public function fontPreload(): ?string
    {
        $settings = $this->documents->content(Handles::SETTINGS);
        $pair = Catalog::fontPair((string) ($settings['values']['font_pair'] ?? '')) ?? (Catalog::fontPairs()[0] ?? null);
        $file = is_array($pair) ? basename((string) ($pair['preload'] ?? '')) : '';
        if ($file === '') {
            return null;
        }

        try {
            return Vite::asset('resources/themes/xelga/assets/fonts/' . $file);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What the editor's preview needs to know about the drawn page: its
     * sections and blocks, which page it is, and how theme settings apply.
     */
    public function pageData(): array
    {
        $targets = [];
        foreach (Catalog::themeSettings() as $key => $setting) {
            $targets[$key] = $setting->raw['target'] ?? null;
        }

        return [
            'protocol' => 1,
            'preview' => true,
            'page' => ['type' => $this->pageType, 'handle' => $this->handle, 'subject' => is_object($this->subject) && isset($this->subject->id) ? (int) $this->subject->id : null],
            'url' => request()->fullUrlWithoutQuery([PreviewMode::QUERY]),
            'sections' => $this->renderer->pageData(),
            'theme_settings' => $targets,
            'font_pairs' => array_map(fn (array $p) => ['value' => $p['value'], 'heading' => $p['heading'], 'body' => $p['body'], 'heading_weight' => $p['heading_weight'] ?? null, 'tokens' => $p['tokens'] ?? (object) []], Catalog::fontPairs()),
            'storage_url' => '/storage/',
        ];
    }

    /** Drop what the last request loaded, so nothing carries over to the next one. */
    public static function forgetRequestState(): void
    {
        foreach ([DocumentRepository::class, SectionRenderer::class, TemplateResolver::class, self::class, \Themes\Xelga\Extensions\Extensions::class] as $class) {
            app()->forgetInstance($class);
        }
        Picture::flush();
        Fold::flush();
        \App\Support\ImageRenditions::flush();
        \Themes\Xelga\Settings\LinkValue::flush();
        \Themes\Xelga\Documents\DocumentPresenter::flush();
    }

    public function hasRoute(string $name): bool
    {
        return Route::has($name);
    }
}
