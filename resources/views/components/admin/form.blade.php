@props(['action', 'method' => 'POST', 'headerSave' => true, 'saveOnlyWhenDirty' => false, 'guard' => true, 'saveLabel' => 'Save', 'acts' => true])
@php
    $spoof = strtoupper($method) !== 'POST' && strtoupper($method) !== 'GET';
    // Unique PER FORM, never per action: several forms on one page can
    // post to the same route (the theme editor's seven sections all do),
    // and a shared id makes every Save button's form="" resolve to the
    // first one. That silently submitted the wrong section.
    $formId = $attributes->get('id') ?: 'vform-' . \Illuminate\Support\Str::random(8);
@endphp
{{-- Every admin form gets dirty tracking, a Save, and the leave guard by
     existing. The one way out is :guard="false": the form then runs on
     vFormPlain, a stand-in that is never dirty and never guards the unload,
     and Save simply stands in the header. It is for pages where browser
     autofill would otherwise raise Unsaved changes on a page nobody touched.

     The Save is a plain button sitting with the page's other actions in the
     header. :acts="false" leaves it out, for a page that draws its own save
     controls inside the form (they still read dirty, saving and discard()
     from this component).

     autocomplete="off": an admin form is a record editor, never a login.
     Browser autofill and reload-time form restoration both write values
     into fields after the baseline is taken, which raised the Unsaved
     changes note on a page nobody had touched. --}}
<form id="{{ $formId }}" method="POST" action="{{ $action }}" x-data="{{ $guard ? 'vForm()' : 'vFormPlain()' }}" autocomplete="off" {{ $attributes->except('id') }}>
    @csrf
    @if ($spoof) @method($method) @endif

    @if ($acts && $headerSave)
        {{-- Teleported: the page header is rendered above and outside this
             form, so the control travels to it and stays wired by form="".
             Alpine keeps teleported markup in this component's scope, so
             dirty and saving still drive it from here. --}}
        <template x-teleport=".v-head">
            <x-admin.form-acts :form-id="$formId" :only-when-dirty="$saveOnlyWhenDirty" :label="$saveLabel" />
        </template>
    @endif

    {{ $slot }}

    {{-- A partial, or one of several forms on a page, keeps its Save beside
         the fields it saves: the page header can only speak for one save
         scope, and two Saves up there would not say which is which. --}}
    @if ($acts && ! $headerSave)
        <div class="v-gap">
            <x-admin.form-acts :form-id="$formId" :only-when-dirty="$saveOnlyWhenDirty" :label="$saveLabel" />
        </div>
    @endif
</form>
