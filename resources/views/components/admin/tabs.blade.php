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
    <div class="flex items-center gap-1 overflow-x-auto border-b border-v-border" role="tablist">{{ $nav }}</div>
    <div class="pt-5">{{ $slot }}</div>
</div>
