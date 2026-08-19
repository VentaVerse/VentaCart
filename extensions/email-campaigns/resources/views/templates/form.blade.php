<x-admin.shell :title="$template ? 'Edit template' : 'New template'">
    @push('head')
        @vite('extensions/email-campaigns/resources/css/email-campaigns.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.email-campaigns.index') }}">Email</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.email-campaigns.templates.index') }}">Templates</a>
                <span aria-hidden="true">/</span>
                <span>{{ $template ? $template->name : 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $template ? 'Edit template' : 'New template' }}</h1>
        </div>
    </div>

    <div class="v-settle">
        <form method="POST" id="v-template-form"
              action="{{ $template ? route('admin.email-campaigns.templates.update', $template) : route('admin.email-campaigns.templates.store') }}">
            @csrf
            @if ($template) @method('PUT') @endif

            <x-admin.panel title="Template">
                <x-admin.field name="name" label="Name" required>
                    <input type="text" id="v-template-name" name="name" required maxlength="128"
                           class="v-control" value="{{ old('name', $template->name ?? '') }}">
                </x-admin.field>

                <x-admin.field name="subject" label="Subject">
                    <input type="text" id="v-template-subject" name="subject" maxlength="255"
                           class="v-control" value="{{ old('subject', $template->subject ?? '') }}">
                </x-admin.field>

                <div class="v-gap">
                    <x-admin.field name="body" label="Body">
                        <div class="v-tokenbar" role="toolbar" aria-label="Insert placeholder">
                            @foreach ($tokens as $key => $label)
                                <button type="button" class="v-token" data-token="{{ $key }}"
                                        onclick="insertTemplateToken(this.dataset.token)">
                                    <span class="v-token__label">{{ $label }}</span>
                                    <span class="v-token__key">{{ '{' . $key . '}' }}</span>
                                </button>
                            @endforeach
                        </div>
                        <textarea id="v-template-body" name="body" rows="16" class="v-control">{{ old('body', $template->body ?? '') }}</textarea>
                    </x-admin.field>
                </div>
            </x-admin.panel>

            <div class="v-gap v-actrow">
                <button type="submit" class="v-btn v-btn--primary">Save template</button>
                <a href="{{ route('admin.email-campaigns.templates.index') }}" class="v-btn v-btn--ghost">Cancel</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v-template-body',
            height: 420,
            menubar: 'file edit view insert format table',
            plugins: 'lists link autolink code table image fullscreen',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | alignleft aligncenter alignright | link image table | removeformat code fullscreen',
            content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; } img { max-width: 100%; height: auto; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            relative_urls: false,
            remove_script_host: true,
            image_advtab: true,
            image_dimensions: true,
            setup: function (editor) {
                editor.on('input change undo redo', function () {
                    editor.save();
                });
            },
        });

        function insertTemplateToken(key) {
            var editor = tinymce.get('v-template-body');
            if (editor) {
                editor.insertContent('{' + key + '}');
                editor.focus();
                editor.save();
            }
        }
    </script>
    @endpush
</x-admin.shell>
