{{-- Adding, renaming and deleting menus. Each is its own small form, drawn
     outside the arrangement form so no form ever sits inside another. A
     form that came back with errors opens again with them. --}}
@can('menus.create')
    @php
        $vAddErrors = $errors->getBag('addMenu');
    @endphp
    <div class="v-passthru" x-data="{ open: @js($vAddErrors->any()) }"
         x-init="if (open) $nextTick(() => $refs.name?.focus())"
         x-on:v-menu-form.window="if ($event.detail === 'add') { open = true; $nextTick(() => $refs.name.focus()); }"
         x-on:keydown.escape.window="open = false">
        <template x-if="open">
            <div class="v-overlay" @click.self="open = false">
                <form method="POST" action="{{ route('admin.menus.store') }}" class="v-modal" role="dialog" aria-modal="true" aria-labelledby="vm-add-title">
                    @csrf
                    <h2 class="v-modal__title" id="vm-add-title">Add a menu</h2>
                    <label class="v-field v-gap-sm">
                        <span class="v-field__label">Menu name</span>
                        <input type="text" name="name" x-ref="name" class="v-control" maxlength="120" required
                               value="{{ $vAddErrors->any() ? old('name') : '' }}" placeholder="Mobile menu">
                        @if ($vAddErrors->has('name'))
                            <span class="v-field__error">{{ $vAddErrors->first('name') }}</span>
                        @endif
                    </label>
                    <div class="v-modal__acts">
                        <button type="button" class="v-btn v-btn--ghost" @click="open = false">Cancel</button>
                        <button type="submit" class="v-btn v-btn--primary">Add menu</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
@endcan

@can('menus.edit')
    @php
        $vRenameErrors = $errors->getBag('renameMenu');
    @endphp
    <div class="v-passthru" x-data="{ open: @js($vRenameErrors->any()) }"
         x-init="if (open) $nextTick(() => $refs.name?.focus())"
         x-on:v-menu-form.window="if ($event.detail === 'rename') { open = true; $nextTick(() => $refs.name.select()); }"
         x-on:keydown.escape.window="open = false">
        <template x-if="open">
            <div class="v-overlay" @click.self="open = false">
                <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="v-modal" role="dialog" aria-modal="true" aria-labelledby="vm-rename-title">
                    @csrf
                    @method('PUT')
                    <h2 class="v-modal__title" id="vm-rename-title">Rename {{ $menu->name }}</h2>
                    <label class="v-field v-gap-sm">
                        <span class="v-field__label">Menu name</span>
                        <input type="text" name="name" x-ref="name" class="v-control" maxlength="120" required
                               value="{{ $vRenameErrors->any() ? old('name') : $menu->name }}">
                        @if ($vRenameErrors->has('name'))
                            <span class="v-field__error">{{ $vRenameErrors->first('name') }}</span>
                        @endif
                    </label>
                    <p class="v-modal__body">Themes that show this menu keep showing it.</p>
                    <div class="v-modal__acts">
                        <button type="button" class="v-btn v-btn--ghost" @click="open = false">Cancel</button>
                        <button type="submit" class="v-btn v-btn--primary">Rename</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
@endcan

@if (! $menu->isRequired())
    @can('menus.delete')
        <x-admin.confirm name="delete-menu" :title="'Delete ' . $menu->name . '?'"
            message="Its items are deleted with it, and wherever the theme shows this menu, nothing shows. This cannot be undone."
            confirm="Delete menu" :action="route('admin.menus.destroy', $menu)" method="DELETE" />
    @endcan
@endif
