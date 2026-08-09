{{-- Three states shown as three controls rather than one that cycles: a
     cycling button never says what the next press will do, and "system" is
     not guessable from a sun. --}}
<div class="v-theme" role="group" aria-label="Theme"
     x-data="{ mode: (window.vTheme && window.vTheme.get()) || 'light' }">
    <button type="button" @click="mode = 'light'; window.vTheme.set('light')"
            :class="mode === 'light' && 'is-on'" :aria-pressed="mode === 'light' ? 'true' : 'false'"
            class="v-theme__btn" aria-label="Light theme">
        <x-admin.icon name="sun" class="w-3.5 h-3.5" />
    </button>
    <button type="button" @click="mode = 'dark'; window.vTheme.set('dark')"
            :class="mode === 'dark' && 'is-on'" :aria-pressed="mode === 'dark' ? 'true' : 'false'"
            class="v-theme__btn" aria-label="Dark theme">
        <x-admin.icon name="moon" class="w-3.5 h-3.5" />
    </button>
    <button type="button" @click="mode = 'system'; window.vTheme.set('system')"
            :class="mode === 'system' && 'is-on'" :aria-pressed="mode === 'system' ? 'true' : 'false'"
            class="v-theme__btn" aria-label="Match system theme">
        <x-admin.icon name="monitor" class="w-3.5 h-3.5" />
    </button>
</div>
