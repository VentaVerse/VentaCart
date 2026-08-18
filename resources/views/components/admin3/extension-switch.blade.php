@props(['name'])
@php
    // The extension's own switch, carried on its configure page (owner
    // ruling: the listing buttons are shortcuts; the module itself also
    // holds its toggle). Renders only while the extension is enabled -
    // in any other state this page does not exist. Flipping it off
    // removes this very page, so the redirect goes to the directory,
    // never "back".
    $manager = app(\App\Services\ExtensionManager::class);
    $isEnabled = $manager->state($name) === 'enabled';
@endphp
@if ($isEnabled)
    <form method="POST" action="{{ route('admin.extensions.disable', $name) }}">
        @csrf
        <input type="hidden" name="_redirect" value="{{ route('admin.extensions.index') }}">
        <label class="v3-toggle">
            <input type="checkbox" checked onchange="this.form.submit()" aria-label="Extension enabled">
            <span class="v3-toggle__track" aria-hidden="true"></span>
            <span>Enabled</span>
        </label>
    </form>
@endif
