{{--
    The theme editor: a full-screen page outside the admin chrome. The editor
    app (editor/js/panel.js) reads its boot payload from #xelga-boot and draws
    itself into #xelga-editor. Until the app is built, a plain page shows the
    published store. Loading this page opens no preview window: the app opens
    one with a POST once it starts.
--}}
@php
    $panelJs = 'resources/themes/xelga/editor/js/panel.js';
    $panelCss = 'resources/themes/xelga/editor/css/panel.css';
    $hasPanel = is_file(base_path($panelJs));
    $hasPanelCss = is_file(base_path($panelCss));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Customize {{ $boot['store']['name'] }} - Xelga</title>
    @if ($hasPanelCss)
        @vite($panelCss)
    @endif
    @if ($hasPanel)
        @vite($panelJs)
    @endif
</head>
<body class="xelga-editor-body">
    <script type="application/json" id="xelga-boot">@json($boot)</script>
    <div id="xelga-editor">
        @unless ($hasPanel)
            <header>
                <h1>Customize {{ $boot['store']['name'] }}</h1>
                <p><a href="{{ $boot['routes']['exit'] }}">Exit to Themes</a></p>
            </header>
            <main>
                <iframe src="{{ $boot['preview']['url'] }}" title="Store preview" width="100%" height="800"></iframe>
            </main>
        @endunless
    </div>
</body>
</html>
