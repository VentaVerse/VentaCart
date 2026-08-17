@props(['title' => 'Admin', 'active' => null])
{{-- One admin, one chrome (owner ruling 2026-08-17). The migrated v3
     bodies render inside the classic shell: the same slab nav template,
     the same topbar (palette search, theme switch, account menu), the
     same toasts and command palette, on every page of the admin. This
     wrapper only adds the v3 stylesheet and the ground class the v3
     bodies are written on; the v3 Alpine pieces (v3Form, the save bar)
     ride the classic bundle via registerV3(). `active` is accepted for
     back-compat and ignored: the classic nav derives the current item
     from the request path. --}}
<x-admin.shell :title="$title">
    @push('head')
        @vite('resources/css/admin3/admin3.css')
    @endpush
    <div class="v3 v3-embed">
        {{ $slot }}
    </div>
</x-admin.shell>
