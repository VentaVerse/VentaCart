@props(['action', 'method' => 'POST', 'headerSave' => true, 'saveOnlyWhenDirty' => false])
@php
    $spoof = strtoupper($method) !== 'POST' && strtoupper($method) !== 'GET';
    // Deterministic, so the header button can name its form across the DOM
    // (form="") and so a test sees a stable id.
    $formId = $attributes->get('id') ?: 'vform-' . substr(sha1($action . $method), 0, 8);
@endphp
{{-- The save doctrine. Every v form gets dirty tracking, a Save, and the
     leave guard by existing; a page cannot opt out of honesty.

     Owner ruling 2026-08-19: the Save is a plain button sitting with the
     page's other actions, the way our own legacy and every other platform
     puts it, rather than a bar occupying the top of the viewport. The
     dirty tracking that bar introduced stays: it is the half that earned
     its keep, and it is what still guards an unsaved exit. --}}
<form id="{{ $formId }}" method="POST" action="{{ $action }}" x-data="vForm()" {{ $attributes->except('id') }}>
    @csrf
    @if ($spoof) @method($method) @endif

    @if ($headerSave)
        {{-- Teleported: the page header is rendered above and outside this
             form, so the control travels to it and stays wired by form="".
             Alpine keeps teleported markup in this component's scope, so
             dirty and saving still drive it from here. --}}
        <template x-teleport=".v-head">
            <x-admin.form-acts :form-id="$formId" :only-when-dirty="$saveOnlyWhenDirty" />
        </template>
    @endif

    {{ $slot }}

    {{-- A partial, or one of several forms on a page, keeps its Save beside
         the fields it saves: the page header can only speak for one save
         scope, and two Saves up there would not say which is which. --}}
    @unless ($headerSave)
        <div class="v-gap">
            <x-admin.form-acts :form-id="$formId" :only-when-dirty="$saveOnlyWhenDirty" />
        </div>
    @endunless
</form>
