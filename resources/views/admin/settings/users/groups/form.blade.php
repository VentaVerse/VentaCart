<x-admin.settings-shell :title="$group ? 'Edit user group' : 'Add user group'" active="user-groups">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.user-groups.index') }}">User Groups</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">
                {{ $group ? $group->name : 'Add user group' }}
                @if (($group?->slug ?? null) === 'super-admin')
                    <span class="v-badge v-badge--warn">Full access</span>
                @endif
            </h1>
            @if ($group)
                <p class="v-head__standing">
                    @if ($group->users_count > 0)
                        <a href="{{ route('admin.users.index', ['group' => $group->id]) }}" class="v-link">{{ $group->users_count }} {{ Str::plural('user', $group->users_count) }}</a> {{ $group->users_count === 1 ? 'holds' : 'hold' }} this access right now.
                    @else
                        No users hold this access yet.
                    @endif
                </p>
            @endif
        </div>
    </div>

    @php
        $locked = ($group?->slug ?? null) === 'super-admin';
        $savedIds = $group?->permissions?->pluck('id')->all() ?? [];
        // old() may only speak when a validation round-trip actually
        // happened; otherwise it silently resurrects DB state over an
        // all-unchecked edit (no permissions[] key posted = fallback).
        $checked = $errors->any() ? old('permissions', []) : $savedIds;
        $ownGroup = $group && auth()->user()->role_id === $group->id;
    @endphp

    <div class="v-passthru" x-data="groupGuard({ own: @js($ownGroup && ! $locked), initial: @js($savedIds) })"
         @keydown.escape.window="open = false">
    <x-admin.form :action="$group ? route('admin.user-groups.update', $group) : route('admin.user-groups.store')"
                   :method="$group ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Group">
            <div class="v-grid v-grid--2">
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v-control" value="{{ old('name', $group->name ?? '') }}">
                </x-admin.field>
                <x-admin.field name="description" label="Description">
                    <input type="text" name="description" class="v-control" value="{{ old('description', $group->description ?? '') }}">
                </x-admin.field>
            </div>
        </x-admin.panel>

        <div class="v-gap">
            <x-admin.panel title="Permissions"
                :note="$locked ? 'Super Admin always holds every permission, including ones added later.' : null">
                @unless ($locked)
                    <x-slot:acts>
                        <button type="button" class="v-link" @click="markAll(true)" aria-label="Grant every permission">Grant all</button>
                        <button type="button" class="v-link" @click="markAll(false)" aria-label="Clear every permission">Clear all</button>
                    </x-slot:acts>
                @endunless

                @error('permissions')
                    <p class="v-field__error">{{ $message }}</p>
                @enderror
                @error('permissions.*')
                    <p class="v-field__error">{{ $message }}</p>
                @enderror

                @foreach ($permissionSections as $section => $groups)
                    <div class="v-permsection">
                        <h3 class="v-permsection__title">{{ $section }}</h3>
                        <div class="v-permgrid">
                            @foreach ($groups as $groupKey => $perms)
                                <fieldset class="v-permblock" x-data="{ mark(on) {
                                        this.$root.querySelectorAll('input[type=checkbox]').forEach(i => {
                                            if (i.checked !== on) { i.checked = on; i.dispatchEvent(new Event('change', { bubbles: true })); }
                                        });
                                    } }">
                                    <legend class="v-field__label v-permblock__legend">{{ Str::headline($groupKey) }}</legend>
                                    @unless ($locked)
                                        <span class="v-permblock__marks">
                                            <button type="button" class="v-link" @click="mark(true)" aria-label="All {{ Str::headline($groupKey) }} permissions">All</button>
                                            <button type="button" class="v-link" @click="mark(false)" aria-label="No {{ Str::headline($groupKey) }} permissions">None</button>
                                        </span>
                                    @endunless
                                    <div class="v-permblock__acts">
                                        @foreach ($perms as $permission)
                                            <label class="v-check">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                                       @disabled($locked)
                                                       @checked($locked || in_array($permission->id, $checked))>
                                                {{ ucfirst(Str::afterLast($permission->slug, '.')) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </x-admin.panel>
        </div>
    </x-admin.form>

    {{-- Self-lockout gate: saving a reduction of YOUR OWN group's access
         deserves a breath before it happens. Capture-phase, registered
         before vForm's listener, so a cancel never half-arms the bar. --}}
    <template x-if="open">
        <div class="v-overlay" @click.self="open = false">
            <div class="v-modal" role="dialog" aria-modal="true" aria-labelledby="vc-own-access">
                <h2 class="v-modal__title" id="vc-own-access">Reduce your own access?</h2>
                <p class="v-modal__body">
                    You are in this group. Saving removes <span x-text="removedCount"></span> <span x-text="removedCount === 1 ? 'permission' : 'permissions'"></span> from your own account, and you may not be able to return to this page.
                </p>
                <div class="v-modal__acts">
                    <button type="button" class="v-btn v-btn--ghost" x-ref="cancel" @click="open = false">Cancel</button>
                    <button type="button" class="v-btn v-btn--danger-fill" @click="proceed()">Save anyway</button>
                </div>
            </div>
        </div>
    </template>
    </div>

    @push('scripts')
    <script>
        function groupGuard({ own, initial }) {
            return {
                open: false,
                removedCount: 0,
                confirmed: false,
                init() {
                    if (! own) return;
                    const form = this.$el.querySelector('form');
                    form.addEventListener('submit', (e) => {
                        if (this.confirmed) return;
                        const now = new Set(Array.from(form.querySelectorAll('input[name="permissions[]"]:checked'), i => i.value));
                        const removed = initial.filter(id => ! now.has(String(id)));
                        if (! removed.length) return;
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        this.removedCount = removed.length;
                        this.open = true;
                        this.$nextTick(() => this.$refs.cancel?.focus());
                    }, true);
                },
                markAll(on) {
                    // $root, not $el: called from a button expression,
                    // where $el is the button itself.
                    this.$root.closest('[x-data^=groupGuard]').querySelectorAll('input[name="permissions[]"]').forEach(i => {
                        if (i.checked !== on) { i.checked = on; i.dispatchEvent(new Event('change', { bubbles: true })); }
                    });
                },
                proceed() {
                    this.confirmed = true;
                    this.open = false;
                    this.$root.closest('[x-data^=groupGuard]').querySelector('form').requestSubmit();
                },
            };
        }
    </script>
    @endpush
</x-admin.settings-shell>
