<x-admin.shell :title="$page ? 'Edit Page' : 'New Page'">
    <x-admin.page :title="$page ? 'Edit Page' : 'New Page'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.pages.index') }}">Back to Pages</x-admin.button>
        </x-slot:actions>

        <form method="POST" action="{{ $page ? route('admin.pages.update', $page) : route('admin.pages.store') }}" data-v-form>
            @csrf
            @if ($page) @method('PUT') @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main content --}}
                <div class="lg:col-span-2 space-y-6">
                    <x-admin.card>
                        <div class="space-y-4">
                            <x-admin.field name="title" label="Title" required>
                                <x-admin.input type="text" name="title" required :value="$page?->title" />
                            </x-admin.field>

                            <x-admin.field name="slug" label="Slug" help="Leave blank to auto-generate from title">
                                <div class="flex items-center gap-1">
                                    <span class="text-[13px] text-v-muted">/</span>
                                    <x-admin.input type="text" name="slug" class="flex-1 font-mono" :value="$page?->slug" placeholder="auto-generated-from-title" />
                                </div>
                            </x-admin.field>

                            <x-admin.field name="body" label="Content">
                                <textarea name="body" class="tinymce-editor v-control w-full text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">{{ old('body', $page?->body) }}</textarea>
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- SEO --}}
                    <x-admin.card title="SEO">
                        <div class="space-y-4">
                            <x-admin.field name="meta_title" label="Meta Title">
                                <x-admin.input type="text" name="meta_title" :value="$page?->meta_title" placeholder="Defaults to page title" />
                            </x-admin.field>
                            <x-admin.field name="meta_description" label="Meta Description">
                                <x-admin.textarea name="meta_description" rows="2" :value="$page?->meta_description" placeholder="Brief description for search engines" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-6">
                    <x-admin.card title="Publish">
                        <div class="space-y-4">
                            <x-admin.toggle name="is_active" :checked="$page?->is_active ?? true" label="Active" />
                            <x-admin.field name="sort_order" label="Sort Order">
                                <x-admin.input type="number" name="sort_order" class="w-24" min="0" :value="$page?->sort_order ?? 0" />
                            </x-admin.field>
                        </div>
                        <div class="mt-6">
                            <x-admin.button type="submit" class="w-full justify-center">
                                {{ $page ? 'Update' : 'Create' }}
                            </x-admin.button>
                        </div>
                        @if ($page)
                            <div class="mt-3 pt-3 border-t border-v-border">
                                <a href="/{{ $page->slug }}" target="_blank" class="text-xs text-v-accent hover:underline">View page <x-admin.icon name="arrow-right" class="inline w-3.5 h-3.5 align-[-2px]" /></a>
                            </div>
                        @endif
                    </x-admin.card>
                </div>
            </div>
        </form>

        @push('scripts')
        <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
        <script>
            tinymce.init({
                selector: '.tinymce-editor',
                height: 450,
                menubar: false,
                plugins: 'lists link autolink code table media',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link table media | removeformat code',
                content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; }',
                branding: false,
                promotion: false,
                license_key: 'gpl',
                relative_urls: false,
                remove_script_host: true,
            });
        </script>
        @endpush
    </x-admin.page>
</x-admin.shell>
