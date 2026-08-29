<?php

namespace Extensions\Reviews;

use Extensions\Reviews\Console\SendReviewRequests;
use Illuminate\Support\ServiceProvider;

class ReviewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The latest approved reviews as a widget, registered by the module
        // that owns reviews so core never learns what one is. Switched off
        // with the extension: a placement then renders nothing and is
        // reported in the Layout editor.
        \App\Support\Widgets::register('reviews.latest', [
            'title' => 'Latest reviews',
            'module' => 'reviews',
            'shape' => 'cards',
            'view' => 'widgets.reviews.latest',
            'schema' => [
                'limit' => ['type' => 'number', 'label' => 'How many', 'default' => 3],
                'columns' => ['type' => 'select', 'label' => 'Per row', 'default' => '3',
                    'options' => ['2' => '2', '3' => '3', '4' => '4']],
                'minimum' => ['type' => 'select', 'label' => 'Stars at least', 'default' => '4',
                    'options' => ['1' => '1', '3' => '3', '4' => '4', '5' => '5']],
            ],
            'resolve' => fn (array $config) => ['reviews' => \Extensions\Reviews\Models\Review::query()
                ->approved()
                ->where('rating', '>=', max(1, min(5, (int) ($config['minimum'] ?? 4))))
                ->with('product')
                ->orderByDesc('published_at')->orderByDesc('id')
                ->limit(max(1, min(12, (int) ($config['limit'] ?? 3))))
                ->get()],
        ]);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'reviews');
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', 'reviews');

        // review:send exists only while this extension is enabled; its
        // Automations row keeps the schedule and fails visibly while it
        // is off (the honest state).
        if ($this->app->runningInConsole()) {
            $this->commands([SendReviewRequests::class]);
        }
    }
}
