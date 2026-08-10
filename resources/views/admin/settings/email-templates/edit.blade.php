<x-admin.settings-shell :title="'Edit: ' . $emailTemplate->name" :wide="true">
        <div class="v-split">
            <div class="v-split__main">
                <x-admin.card>
                    <form method="POST" action="{{ route('admin.email-templates.update', $emailTemplate) }}" id="template-form" data-v-form>
                        @csrf
                        @method('PUT')

                        <div class="space-y-5">
                            <x-admin.field name="subject" label="Subject" required>
                                <x-admin.input type="text" name="subject" :value="$emailTemplate->subject" />
                            </x-admin.field>

                            <x-admin.field name="body" label="Body (HTML)" required>
                                <textarea name="body" id="body" class="tinymce-editor v-control w-full rounded-v-md border bg-v-surface text-[13px] text-v-text">{{ old('body', $emailTemplate->body) }}</textarea>
                            </x-admin.field>
                        </div>

                        <x-admin.form-actions>
                            <x-admin.button type="submit">Save Template</x-admin.button>
                        </x-admin.form-actions>
                    </form>
                </x-admin.card>

                <form method="POST" action="{{ route('admin.email-templates.send-test', $emailTemplate) }}" data-v-form class="flex items-center gap-2">
                    @csrf
                    <x-admin.button type="submit" variant="secondary">Send a test to {{ auth()->user()->email }}</x-admin.button>
                    <x-admin.hint label="What a test email contains">Sample order and customer data, so every placeholder resolves to something readable.</x-admin.hint>
                </form>
            </div>

            <div class="v-split__rail">
                <x-admin.card title="Template">
                    <dl class="v-facts v-facts--flush">
                        <dt class="v-label">Slug</dt>
                        <dd class="v-num text-xs">{{ $emailTemplate->slug }}</dd>
                        <dt class="v-label">Type</dt>
                        <dd>
                            <x-admin.badge :tone="$emailTemplate->type === 'customer' ? 'info' : 'neutral'">
                                {{ $emailTemplate->type === 'customer' ? 'Customer' : 'Admin' }}
                            </x-admin.badge>
                        </dd>
                        <dt class="v-label">Status</dt>
                        <dd>
                            <x-admin.badge :tone="$emailTemplate->is_active ? 'success' : 'neutral'">
                                {{ $emailTemplate->is_active ? 'Active' : 'Disabled' }}
                            </x-admin.badge>
                        </dd>
                    </dl>
                </x-admin.card>

                @if (count($placeholders) > 0)
                    <x-admin.card title="Placeholders">
                        <div class="space-y-1.5">
                            @foreach ($placeholders as $placeholder)
                                <button type="button" onclick="copyPlaceholder(this, '{{ $placeholder }}')"
                                    title="Copy to clipboard"
                                    class="w-full cursor-pointer rounded-v-md border border-v-border bg-v-sunken px-3 py-1.5 text-left transition-colors hover:bg-v-hover">
                                    <span class="v-num text-xs text-v-warning">{!! '{' . e($placeholder) . '}' !!}</span>
                                </button>
                            @endforeach
                        </div>
                    </x-admin.card>
                @endif
            </div>
        </div>

        @push('scripts')
        <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
        <script>
            function copyPlaceholder(btn, name) {
                var text = '{' + name + '}';
                navigator.clipboard.writeText(text).then(function() {
                    var span = btn.querySelector('span');
                    span.textContent = 'Copied!';
                    setTimeout(function() { span.textContent = text; }, 1500);
                });
            }
        </script>
        <script>
            tinymce.init({
                selector: '.tinymce-editor',
                height: 450,
                menubar: false,
                plugins: 'lists link autolink code table',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | bullist numlist | link table | removeformat code',
                content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; background-color: #1a1a1a; color: #e8e8e8; } a { color: #F5A623; }',
                branding: false,
                promotion: false,
                license_key: 'gpl',
                convert_urls: false,
            });
        </script>
        @endpush
</x-admin.settings-shell>
