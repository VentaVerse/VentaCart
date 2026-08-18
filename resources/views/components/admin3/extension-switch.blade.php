@props(['name'])
@php
    // The extension's own switch, carried on its configure page (owner
    // ruling: the listing buttons are shortcuts; the module itself also
    // holds its toggle). Renders only while the extension is enabled -
    // in any other state this page does not exist.
    //
    // Owner ruling 2026-08-20: NEVER auto-submits. Flipping the toggle
    // arms the standard v3 save bar (the one save doctrine every v3
    // form follows); only Save performs the disable. Since disabling
    // removes this very page, the redirect goes to the directory, never
    // "back". The hidden "1" beside the checkbox keeps the v3Form
    // snapshot honest: checked reads 1&1, unchecked reads 1, so
    // flipping it back settles the bar without a reload.
    $manager = app(\App\Services\ExtensionManager::class);
    $isEnabled = $manager->state($name) === 'enabled';
@endphp
@if ($isEnabled)
    <x-admin3.form :action="route('admin.extensions.disable', $name)">
        <input type="hidden" name="_redirect" value="{{ route('admin.extensions.index') }}">
        <label class="v3-toggle">
            <input type="hidden" name="_extension_enabled" value="1">
            <input type="checkbox" name="_extension_enabled" value="1" checked aria-label="Extension enabled">
            <span class="v3-toggle__track" aria-hidden="true"></span>
            <span>Enabled</span>
        </label>
    </x-admin3.form>
@endif
