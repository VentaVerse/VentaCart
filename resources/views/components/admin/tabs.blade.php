{{-- The Alpine-driven tab set. Its nav is now the shared segment bar,
     so a tab set and a phase filter are the same control wearing the
     same trough, whichever mechanism swaps the view underneath.

     The prop API and the Alpine scope are unchanged: `default`,
     `param`, `names`, `pick()` and the ?tab= address rewriting all
     behave exactly as before. --}}
@props(['default', 'param' => 'tab', 'names' => null])
<div x-data="{
        tab: @js(request()->query($param, $default)),
        param: @js($param),
        names: @js($names),
        init() {
            if (this.names && !this.names.includes(this.tab)) {
                this.tab = @js($default);
            }
        },
        pick(name) {
            this.tab = name;
            const url = new URL(window.location);
            url.searchParams.set(this.param, name);
            history.replaceState({}, '', url);
        },
    }" {{ $attributes->merge([]) }}>
    <div class="v-segbar">
        <div class="v-segment" role="tablist">{{ $nav }}</div>
    </div>
    <div class="pt-2">{{ $slot }}</div>
</div>
