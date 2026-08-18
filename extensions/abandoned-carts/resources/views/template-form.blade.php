<x-abandoned-carts::space :title="$emailTemplate ? 'Edit Template' : 'Create Template'" active="templates">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.abandoned-carts.templates') }}">Abandoned Carts</a>
                <span aria-hidden="true">/</span>
                <span>{{ $emailTemplate ? $emailTemplate->name : 'New template' }}</span>
            </div>
            <h1 class="v-head__title">{{ $emailTemplate ? $emailTemplate->name : 'Create Template' }}</h1>
        </div>
    </div>

    <div class="v-split v-settle">
        <x-admin.form :action="$emailTemplate ? route('admin.abandoned-carts.templates.update', $emailTemplate) : route('admin.abandoned-carts.templates.store')"
                       :method="$emailTemplate ? 'PUT' : 'POST'" id="v-ac-template-form">
            <x-admin.panel title="Content">
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v-control" value="{{ old('name', $emailTemplate?->name) }}">
                </x-admin.field>
                @if (! $emailTemplate)
                    <div class="v-gap">
                        <x-admin.field name="slug" label="Slug" required hint="Starts with abandoned_cart_, lowercase and underscores.">
                            <input type="text" name="slug" required class="v-control v-mono" value="{{ old('slug', 'abandoned_cart_') }}">
                        </x-admin.field>
                    </div>
                @endif
                <div class="v-gap">
                    <x-admin.field name="subject" label="Subject" required>
                        <input type="text" name="subject" required class="v-control" value="{{ old('subject', $emailTemplate?->subject) }}">
                    </x-admin.field>
                </div>
                <div class="v-gap">
                    <x-admin.field name="body" label="Body" required>
                        <textarea name="body" id="v-ac-template-body" class="v-control">{{ old('body', $emailTemplate?->body) }}</textarea>
                    </x-admin.field>
                </div>
            </x-admin.panel>
        </x-admin.form>

        <div>
            @if (! empty($placeholders))
                <x-admin.panel title="Placeholders">
                    @foreach ($placeholders as $placeholder)
                        <button type="button" class="v-chip"
                                @click="vCopy(@js('{' . $placeholder . '}')).then(ok => { if (!ok) return; const s = $el; const t = s.textContent; s.textContent = 'Copied'; setTimeout(() => s.textContent = t, 1500); })">{{ '{' . $placeholder . '}' }}</button>
                    @endforeach
                </x-admin.panel>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v-ac-template-body',
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
                    var form = document.getElementById('v-ac-template-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });
    </script>
    @endpush
</x-abandoned-carts::space>
