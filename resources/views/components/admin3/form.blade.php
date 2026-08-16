@props(['action', 'method' => 'POST'])
@php $spoof = strtoupper($method) !== 'POST' && strtoupper($method) !== 'GET'; @endphp
{{-- The save doctrine. Every v3 form gets dirty tracking, the save bar,
     and the leave guard by existing; a page cannot opt out of honesty. --}}
<form method="POST" action="{{ $action }}" x-data="v3Form()" {{ $attributes }}>
    @csrf
    @if ($spoof) @method($method) @endif

    <div class="v3-savebar" :class="(dirty || saving) && 'is-on'" :inert="!(dirty || saving)" inert>
        <span class="v3-savebar__word" role="status">
            <span class="v3-savebar__dot" aria-hidden="true"></span>
            <span x-text="!(dirty || saving) ? '' : (saving ? 'Saving…' : 'Unsaved changes')">Unsaved changes</span>
        </span>
        <span class="v3-savebar__acts">
            <button type="button" class="v3-btn v3-btn--ghost" @click="discard()" :disabled="saving">Discard</button>
            <button type="submit" class="v3-btn v3-btn--primary" :disabled="saving">
                <span x-text="saving ? 'Saving…' : 'Save'">Save</span>
            </button>
        </span>
    </div>

    {{ $slot }}
</form>
