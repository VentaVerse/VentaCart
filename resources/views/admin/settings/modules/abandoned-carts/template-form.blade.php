<x-admin.shell :title="$emailTemplate ? 'Edit Template' : 'Create Template'">
    <x-admin.page :title="$emailTemplate ? 'Edit Template' : 'Create Template'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.abandoned-carts.templates') }}">Cancel</x-admin.button>
        </x-slot:actions>

        <div class="max-w-4xl">
            <x-admin.card>
                <form method="POST" action="{{ $emailTemplate ? route('admin.abandoned-carts.templates.update', $emailTemplate) : route('admin.abandoned-carts.templates.store') }}"
                    data-v-form class="space-y-6">
                    @csrf
                    @if ($emailTemplate) @method('PUT') @endif

                    <x-admin.field name="name" label="Name" required>
                        <x-admin.input name="name" :value="$emailTemplate?->name" required />
                    </x-admin.field>

                    @if (!$emailTemplate)
                        <x-admin.field name="slug" label="Slug"
                            help="Must start with abandoned_cart_. Use lowercase and underscores." required>
                            <x-admin.input name="slug" :value="old('slug', 'abandoned_cart_')" required class="font-mono" />
                        </x-admin.field>
                    @endif

                    <x-admin.field name="subject" label="Subject" required>
                        <x-admin.input name="subject" :value="$emailTemplate?->subject" required />
                    </x-admin.field>

                    @if (!empty($placeholders))
                        <div class="rounded-v-md bg-v-sunken p-4">
                            <p class="mb-2 v-label">Available Placeholders <span class="font-normal normal-case">(click to copy)</span></p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($placeholders as $placeholder)
                                    <button type="button" onclick="copyPlaceholder(this, '{{ $placeholder }}')"
                                        class="cursor-pointer rounded-v-md border border-v-border bg-v-surface px-2.5 py-1 text-xs text-v-text transition-colors hover:border-v-accent hover:bg-v-accent-weak">
                                        <span>{!! '{' . $placeholder . '}' !!}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-admin.field name="body" label="Body" required>
                        <textarea name="body" id="body" class="tinymce-editor v-control w-full rounded-v-md border border-v-border bg-v-surface px-3 py-2 text-[13px] text-v-text">{{ old('body', $emailTemplate?->body) }}</textarea>
                    </x-admin.field>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" href="{{ route('admin.abandoned-carts.templates') }}">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $emailTemplate ? 'Save Template' : 'Create Template' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        function copyPlaceholder(btn, name) {
            var text = '{' + name + '}';
            window.vCopy(text).then(function (ok) {
                if (!ok) return;
                var span = btn.querySelector('span');
                span.textContent = 'Copied';
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
</x-admin.shell>
