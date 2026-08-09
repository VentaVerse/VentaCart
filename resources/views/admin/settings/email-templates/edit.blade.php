<x-admin.settings-shell :title="'Edit: ' . $emailTemplate->name" :wide="true">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Form --}}
            <div class="lg:col-span-2 space-y-4">
                <x-admin.card>
                    <form method="POST" action="{{ route('admin.email-templates.update', $emailTemplate) }}" id="template-form" data-v-form>
                        @csrf
                        @method('PUT')

                        <div class="space-y-5">
                            <x-admin.field name="subject" label="Subject" required>
                                <x-admin.input type="text" name="subject" :value="$emailTemplate->subject" />
                            </x-admin.field>

                            <x-admin.field name="body" label="Body (HTML)" required>
                                <textarea name="body" id="body" class="tinymce-editor v-control w-full text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">{{ old('body', $emailTemplate->body) }}</textarea>
                            </x-admin.field>
                        </div>

                        <x-admin.form-actions>
                            <x-admin.button type="submit">Save Template</x-admin.button>
                        </x-admin.form-actions>
                    </form>
                </x-admin.card>

                <form method="POST" action="{{ route('admin.email-templates.send-test', $emailTemplate) }}" data-v-form class="flex items-center gap-2">
                    @csrf
                    <x-admin.button type="submit" variant="secondary">Send Test Email</x-admin.button>
                    <span class="text-xs text-v-muted">Sends to your admin email with sample data</span>
                </form>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-5">
                {{-- Template Info --}}
                <x-admin.card title="Template Info">
                    <dl class="space-y-2.5 text-[13px]">
                        <div class="flex justify-between">
                            <dt class="text-v-secondary">Slug</dt>
                            <dd class="v-num text-xs text-v-text">{{ $emailTemplate->slug }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-v-secondary">Type</dt>
                            <dd>
                                <x-admin.badge :tone="$emailTemplate->type === 'customer' ? 'info' : 'neutral'">
                                    {{ $emailTemplate->type === 'customer' ? 'Customer' : 'Admin' }}
                                </x-admin.badge>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-v-secondary">Status</dt>
                            <dd>
                                <x-admin.badge :tone="$emailTemplate->is_active ? 'success' : 'neutral'">
                                    {{ $emailTemplate->is_active ? 'Active' : 'Disabled' }}
                                </x-admin.badge>
                            </dd>
                        </div>
                    </dl>
                </x-admin.card>

                {{-- Placeholders --}}
                @if (count($placeholders) > 0)
                    <x-admin.card title="Available Placeholders">
                        <p class="mb-3 text-xs text-v-secondary">Click to copy. Use these in the subject or body.</p>
                        <div class="space-y-1.5">
                            @foreach ($placeholders as $placeholder)
                                <button type="button" onclick="copyPlaceholder(this, '{{ $placeholder }}')"
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
