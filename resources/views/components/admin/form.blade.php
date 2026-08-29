@props(['action', 'method' => 'POST', 'headerSave' => true, 'saveOnlyWhenDirty' => false, 'guard' => true])
@php
    $spoof = strtoupper($method) !== 'POST' && strtoupper($method) !== 'GET';
    // Unique PER FORM, never per action: several forms on one page can
    // post to the same route (the theme editor's seven sections all do),
    // and a shared id makes every Save button's form="" resolve to the
    // first one. That silently submitted the wrong section.
    $formId = $attributes->get('id') ?: 'vform-' . \Illuminate\Support\Str::random(8);
@endphp
{{-- The save doctrine. Every v form gets dirty tracking, a Save, and the
     leave guard by existing. The one way out is :guard="false", which the
     owner grants per page (the customer editor, 2026-08-29, after browser
     autofill kept raising Unsaved changes on a page nobody had touched):
     the form then runs on vFormPlain, a stand-in that is never dirty and
     never guards the unload, and Save simply stands in the header.

     Owner ruling 2026-08-19: the Save is a plain button sitting with the
     page's other actions, the way our own legacy and every other platform
     puts it, rather than a bar occupying the top of the viewport. The
     dirty tracking that bar introduced stays: it is the half that earned
     its keep, and it is what still guards an unsaved exit. --}}
{{-- autocomplete="off": a doctrine form is a record editor, never a login.
     Browser autofill and reload-time form restoration both write values
     into fields after the baseline is taken, which raised the Unsaved
     changes bar on a page nobody had touched. --}}
<form id="{{ $formId }}" method="POST" action="{{ $action }}" x-data="{{ $guard ? 'vForm()' : 'vFormPlain()' }}" autocomplete="off" {{ $attributes->except('id') }}>
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
