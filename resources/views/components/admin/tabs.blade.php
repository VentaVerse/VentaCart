@props(['default', 'param' => 'tab'])
<div x-data="{
        tab: @js(request()->query($param, $default)),
        param: @js($param),
        pick(name) {
            this.tab = name;
            const url = new URL(window.location);
            url.searchParams.set(this.param, name);
            history.replaceState({}, '', url);
        },
    }" {{ $attributes }}>
    <div class="flex items-center gap-1 border-b border-v-border" role="tablist">{{ $nav }}</div>
    <div class="pt-5">{{ $slot }}</div>
</div>
