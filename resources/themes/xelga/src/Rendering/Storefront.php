<?php

namespace Themes\Xelga\Rendering;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Handles;
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

        $base = Handles::baseFor($pageType);
        $content = self::withMainSection($this->documents->content($this->handle), $base);

        return new HtmlString($this->renderer->render(
            $content,
            new RenderContext($pageType, $this->handle, 'template', $this->preview(), $subject, $page),
        ));
    }

    /**
     * A template always draws its page's main section (the product, the
     * listing, the cart). The editor cannot remove it, but a stored document
     * that lost it would leave the page without its content, so the shipped
     * one is put back in its usual place. Main sections are never hidden.
     */
    public static function withMainSection(array $content, string $base): array
    {
        $sections = (array) ($content['sections'] ?? []);
        $order = array_values(array_filter((array) ($content['order'] ?? []), 'is_string'));

        foreach ($order as $id) {
            $schema = Catalog::section((string) ($sections[$id]['type'] ?? ''));
            if ($schema !== null && ($schema->raw['main'] ?? false) === true) {
                $content['sections'][$id]['disabled'] = false;

                return $content;
            }
        }

        $defaults = Defaults::content($base);
        foreach ((array) ($defaults['order'] ?? []) as $index => $id) {
            $section = $defaults['sections'][$id] ?? null;
            $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
            if ($schema === null || ($schema->raw['main'] ?? false) !== true || isset($sections[$id])) {
                continue;
            }
            array_splice($order, min($index, count($order)), 0, [$id]);
            $content['sections'] = [...$sections, $id => $section];
            $content['order'] = $order;

            return $content;
        }

        return $content;
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
            $this->documents->content($handle),
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
        \Themes\Xelga\Settings\LinkValue::flush();
        \Themes\Xelga\Documents\DocumentPresenter::flush();
    }

    public function hasRoute(string $name): bool
    {
        return Route::has($name);
    }
}
