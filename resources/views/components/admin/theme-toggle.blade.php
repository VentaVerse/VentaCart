<div x-data="{
        pref: (() => {
            try {
                return localStorage.getItem('vTheme') || 'system';
            } catch (e) {
                return 'system';
            }
        })(),
        cycle() {
            this.pref = this.pref === 'system' ? 'light' : (this.pref === 'light' ? 'dark' : 'system');
            try {
                localStorage.setItem('vTheme', this.pref);
            } catch (e) {
                // Ignore storage errors (e.g. Safari private mode)
            }
            this.apply();
        },
        apply() {
            const dark = this.pref === 'dark' || (this.pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        },
    }">
    <button type="button" @click="cycle()" class="p-1.5 rounded-v-md text-v-secondary hover:text-v-text hover:bg-v-hover"
            :title="'Theme: ' + pref">
        <x-admin.icon name="sun" x-show="pref === 'light'" x-cloak class="w-4 h-4" />
        <x-admin.icon name="moon" x-show="pref === 'dark'" x-cloak class="w-4 h-4" />
        <x-admin.icon name="monitor" x-show="pref === 'system'" class="w-4 h-4" />
    </button>
</div>
