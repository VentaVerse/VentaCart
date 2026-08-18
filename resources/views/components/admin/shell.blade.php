@props(['title' => 'Admin', 'active' => null])
{{-- One admin, one chrome (owner ruling 2026-08-17). The migrated v
     bodies render inside the classic shell: the same slab nav template,
     the same topbar (palette search, theme switch, account menu), the
     same toasts and command palette, on every page of the admin. This
     wrapper only adds the v stylesheet and the ground class the v
     bodies are written on; the v Alpine pieces (vForm, the save bar)
     ride the classic bundle via registerV(). `active` is accepted for
     back-compat and ignored: the classic nav derives the current item
     from the request path. --}}
<x-admin.chrome :title="$title">
    <div class="v-ui v-embed">
        {{ $slot }}
    </div>
</x-admin.chrome>
