<x-admin3.shell :title="$page ? 'Edit Page' : 'New Page'" active="content">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <span>Content</span>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.pages.index') }}">Pages</a>
                <span aria-hidden="true">/</span>
                <span>{{ $page->title ?? 'New Page' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $page ? $page->title : 'New page' }}</h1>
        </div>
        @if ($page)
            <div class="v3-head__actions">
                <a href="/{{ $page->slug }}" target="_blank" class="v3-btn v3-btn--secondary">View page</a>
            </div>
        @endif
    </div>

    <x-admin3.form id="v3-page-form" :action="$page ? route('admin.pages.update', $page) : route('admin.pages.store')"
                   :method="$page ? 'PUT' : 'POST'" class="v3-settle">
        <x-admin3.panel title="Page">
            <x-admin3.field name="title" label="Title" required>
                <input type="text" name="title" required class="v3-control" value="{{ old('title', $page?->title) }}">
            </x-admin3.field>
            <div class="v3-gap">
                <x-admin3.field name="slug" label="Slug" hint="Leave blank to auto-generate from the title.">
                    <input type="text" name="slug" class="v3-control v3-mono" value="{{ old('slug', $page?->slug) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="body" label="Content">
                    <textarea name="body" id="v3-page-body" class="v3-control">{{ old('body', $page?->body) }}</textarea>
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="SEO">
            <x-admin3.field name="meta_title" label="Meta title" hint="Defaults to the page title.">
                <input type="text" name="meta_title" class="v3-control" value="{{ old('meta_title', $page?->meta_title) }}">
            </x-admin3.field>
            <div class="v3-gap">
                <x-admin3.field name="meta_description" label="Meta description">
                    <textarea name="meta_description" rows="2" class="v3-control">{{ old('meta_description', $page?->meta_description) }}</textarea>
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Publish">
            <div class="v3-actrow">
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page?->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    <span>Active</span>
                </label>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v3-control v3-mono v3-w-sort" value="{{ old('sort_order', $page?->sort_order ?? 0) }}">
                </x-admin3.field>
            </div>
        </x-admin3.panel>
    </x-admin3.form>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v3-page-body',
            height: 450,
            menubar: false,
            plugins: 'lists link autolink code table media',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link table media | removeformat code',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            relative_urls: false,
            remove_script_host: true,
            setup: function (editor) {
                // The editor lives outside the form's input events; sync it
                // back to the textarea and tell the save doctrine on every
                // edit, so the save bar and leave guard see TinyMCE changes.
                editor.on('input change undo redo', function () {
                    editor.save();
                    var form = document.getElementById('v3-page-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });
    </script>
    @endpush
</x-admin3.shell>
