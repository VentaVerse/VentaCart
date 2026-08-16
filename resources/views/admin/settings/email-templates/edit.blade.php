<x-admin3.settings-shell :title="$emailTemplate->name" active="email-templates">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.email-templates.index') }}">Email Templates</a>
                <span aria-hidden="true">/</span>
                <span>{{ $emailTemplate->name }}</span>
            </div>
            <h1 class="v3-head__title">{{ $emailTemplate->name }}</h1>
        </div>
    </div>

    <div class="v3-split v3-settle">
        <x-admin3.form :action="route('admin.email-templates.update', $emailTemplate)" method="PUT" id="v3-template-form">
            <x-admin3.panel title="Content">
                <x-admin3.field name="subject" label="Subject" required>
                    <input type="text" name="subject" required class="v3-control" value="{{ old('subject', $emailTemplate->subject) }}">
                </x-admin3.field>
                <div class="v3-gap">
                    <x-admin3.field name="body" label="Body" required>
                        <textarea name="body" id="v3-template-body" class="v3-control">{{ old('body', $emailTemplate->body) }}</textarea>
                    </x-admin3.field>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="Send a test" note="Sample order and customer data, so every placeholder resolves to something readable.">
                <div x-data="{ sending: false }">
                    <button type="button" class="v3-btn v3-btn--secondary" :disabled="sending"
                            @click="sending = true; v3Post(@js(route('admin.email-templates.send-test', $emailTemplate))).finally(() => sending = false)">
                        <span x-text="sending ? 'Sending…' : @js('Send a test to ' . auth()->user()->email)">Send a test to {{ auth()->user()->email }}</span>
                    </button>
                </div>
            </x-admin3.panel>
        </x-admin3.form>

        <div>
            <x-admin3.panel title="Template">
                <dl class="v3-facts">
                    <dt>Slug</dt>
                    <dd class="v3-mono">{{ $emailTemplate->slug }}</dd>
                    <dt>Type</dt>
                    <dd><span class="v3-badge {{ $emailTemplate->type === 'customer' ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $emailTemplate->type === 'customer' ? 'Customer' : 'Admin' }}</span></dd>
                    <dt>Status</dt>
                    <dd><span class="v3-badge {{ $emailTemplate->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $emailTemplate->is_active ? 'Active' : 'Disabled' }}</span></dd>
                </dl>
            </x-admin3.panel>

            @if (count($placeholders) > 0)
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
            selector: '#v3-template-body',
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
                    var form = document.getElementById('v3-template-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });
    </script>
    @endpush
</x-admin3.settings-shell>
