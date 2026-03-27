<x-admin-layout>
    <x-slot name="header">{{ $emailTemplate ? 'Edit Template' : 'Create Template' }}</x-slot>

    <div class="max-w-4xl">
        <form method="POST" action="{{ $emailTemplate ? route('admin.abandoned-carts.templates.update', $emailTemplate) : route('admin.abandoned-carts.templates.store') }}" class="space-y-6">
            @csrf
            @if ($emailTemplate) @method('PUT') @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $emailTemplate?->name) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (!$emailTemplate)
                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                    <input type="text" name="slug" id="slug" value="{{ old('slug', 'abandoned_cart_') }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                    <p class="mt-1 text-xs text-gray-400">Must start with <code class="bg-gray-100 px-1 rounded">abandoned_cart_</code>. Use lowercase and underscores.</p>
                    @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject', $emailTemplate?->subject) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (!empty($placeholders))
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Available Placeholders <span class="font-normal normal-case">(click to copy)</span></p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($placeholders as $placeholder)
                            <button type="button" onclick="copyPlaceholder(this, '{{ $placeholder }}')"
                                class="px-2.5 py-1 bg-white rounded border border-gray-200 text-xs text-gray-700 hover:bg-indigo-50 hover:border-indigo-300 transition-colors cursor-pointer">
                                <span>{!! '{' . $placeholder . '}' !!}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Body</label>
                <textarea name="body" id="body" class="tinymce-editor w-full rounded-lg border-gray-300 text-sm shadow-sm">{{ old('body', $emailTemplate?->body) }}</textarea>
                @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">{{ $emailTemplate ? 'Save Template' : 'Create Template' }}</button>
                <a href="{{ route('admin.abandoned-carts.templates') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            </div>
        </form>
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
</x-admin-layout>
