<x-admin.settings-shell :title="$emailTemplate->name" active="email-templates">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.email-templates.index') }}">Email Templates</a>
                <span aria-hidden="true">/</span>
                <span>{{ $emailTemplate->name }}</span>
            </div>
            <h1 class="v-head__title">{{ $emailTemplate->name }}</h1>
        </div>
    </div>

    <div class="v-split v-settle">
        <x-admin.form :action="route('admin.email-templates.update', $emailTemplate)" method="PUT" id="v-template-form">
            <x-admin.panel title="Content">
                <x-admin.field name="subject" label="Subject" required>
                    <input type="text" name="subject" required class="v-control" value="{{ old('subject', $emailTemplate->subject) }}">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="body" label="Body" required>
                        <textarea name="body" id="v-template-body" class="v-control">{{ old('body', $emailTemplate->body) }}</textarea>
                    </x-admin.field>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Send a test" note="Sample order and customer data, so every placeholder resolves to something readable.">
                <div x-data="{ sending: false }">
                    <button type="button" class="v-btn v-btn--secondary" :disabled="sending"
                            @click="sending = true; vPost(@js(route('admin.email-templates.send-test', $emailTemplate))).finally(() => sending = false)">
                        <span x-text="sending ? 'Sending…' : @js('Send a test to ' . auth()->user()->email)">Send a test to {{ auth()->user()->email }}</span>
                    </button>
                </div>
            </x-admin.panel>
        </x-admin.form>

        <div>
            <x-admin.panel title="Template">
                <dl class="v-facts">
                    <dt>Slug</dt>
                    <dd class="v-mono">{{ $emailTemplate->slug }}</dd>
                    <dt>Type</dt>
                    <dd><span class="v-badge {{ $emailTemplate->type === 'customer' ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $emailTemplate->type === 'customer' ? 'Customer' : 'Admin' }}</span></dd>
                    <dt>Status</dt>
                    <dd><span class="v-badge {{ $emailTemplate->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $emailTemplate->is_active ? 'Active' : 'Disabled' }}</span></dd>
                </dl>
            </x-admin.panel>

            @if (count($placeholders) > 0)
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
            selector: '#v-template-body',
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
                    var form = document.getElementById('v-template-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });
    </script>
    @endpush
</x-admin.settings-shell>
