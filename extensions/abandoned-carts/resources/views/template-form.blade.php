<x-admin3.shell :title="$emailTemplate ? 'Edit Template' : 'Create Template'">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.abandoned-carts.templates') }}">Abandoned Carts</a>
                <span aria-hidden="true">/</span>
                <span>{{ $emailTemplate ? $emailTemplate->name : 'New template' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $emailTemplate ? $emailTemplate->name : 'Create Template' }}</h1>
        </div>
    </div>

    <div class="v3-split v3-settle">
        <x-admin3.form :action="$emailTemplate ? route('admin.abandoned-carts.templates.update', $emailTemplate) : route('admin.abandoned-carts.templates.store')"
                       :method="$emailTemplate ? 'PUT' : 'POST'" id="v3-ac-template-form">
            <x-admin3.panel title="Content">
                <x-admin3.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v3-control" value="{{ old('name', $emailTemplate?->name) }}">
                </x-admin3.field>
                @if (! $emailTemplate)
                    <div class="v3-gap">
                        <x-admin3.field name="slug" label="Slug" required hint="Starts with abandoned_cart_, lowercase and underscores.">
                            <input type="text" name="slug" required class="v3-control v3-mono" value="{{ old('slug', 'abandoned_cart_') }}">
                        </x-admin3.field>
                    </div>
                @endif
                <div class="v3-gap">
                    <x-admin3.field name="subject" label="Subject" required>
                        <input type="text" name="subject" required class="v3-control" value="{{ old('subject', $emailTemplate?->subject) }}">
                    </x-admin3.field>
                </div>
                <div class="v3-gap">
                    <x-admin3.field name="body" label="Body" required>
                        <textarea name="body" id="v3-ac-template-body" class="v3-control">{{ old('body', $emailTemplate?->body) }}</textarea>
                    </x-admin3.field>
                </div>
            </x-admin3.panel>
        </x-admin3.form>

        <div>
            @if (! empty($placeholders))
                <x-admin3.panel title="Placeholders">
                    @foreach ($placeholders as $placeholder)
                        <button type="button" class="v3-chip"
                                @click="v3Copy(@js('{' . $placeholder . '}')).then(ok => { if (!ok) return; const s = $el; const t = s.textContent; s.textContent = 'Copied'; setTimeout(() => s.textContent = t, 1500); })">{{ '{' . $placeholder . '}' }}</button>
                    @endforeach
                </x-admin3.panel>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v3-ac-template-body',
            height: 450,
            menubar: false,
            plugins: 'lists link autolink code table',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | bullist numlist | link table | removeformat code',
            content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; background-color: #1a1a1a; color: #e8e8e8; } a { color: #F5A623; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            convert_urls: false,
            setup: function (editor) {
                // The editor lives outside the form's input events; sync it
                // back to the textarea and tell the save doctrine on every
                // edit, so the save bar and leave guard see TinyMCE changes.
                editor.on('input change undo redo', function () {
                    editor.save();
                    var form = document.getElementById('v3-ac-template-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });
    </script>
    @endpush
</x-admin3.shell>
