<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>No theme installed</title>
    @vite('resources/css/storefront/no-theme.css')
</head>
<body class="no-theme">
    <main class="no-theme__card">
        <h1>No theme installed</h1>
        <p>{{ $siteName }} has no storefront theme to draw its pages with.</p>
    </main>
</body>
</html>
