<x-admin-layout>
    <x-slot name="title">Edit Template: {{ $emailTemplate->name }}</x-slot>
    <x-slot name="header">Edit: {{ $emailTemplate->name }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.email-templates.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
            Back
        </a>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.email-templates.update', $emailTemplate) }}" id="template-form">
                @csrf
                @method('PUT')

                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="p-5 space-y-5">
                        {{-- Subject --}}
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-1.5">Subject</label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject', $emailTemplate->subject) }}" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('subject')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Body --}}
                        <div>
                            <label for="body" class="block text-sm font-medium text-gray-700 mb-1.5">Body (HTML)</label>
                            <textarea name="body" id="body" class="tinymce-editor w-full rounded-lg border-gray-300 text-sm shadow-sm">{{ old('body', $emailTemplate->body) }}</textarea>
                            @error('body')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            Save Template
                        </button>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.email-templates.send-test', $emailTemplate) }}" class="mt-4">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    Send Test Email
                </button>
                <span class="ml-2 text-xs text-gray-500">Sends to your admin email with sample data</span>
            </form>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-5">
            {{-- Template Info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Template Info</h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Slug</dt>
                        <dd class="font-mono text-xs text-gray-700">{{ $emailTemplate->slug }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Type</dt>
                        <dd>
                            @if ($emailTemplate->type === 'customer')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">Customer</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">Admin</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            @if ($emailTemplate->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">Disabled</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Placeholders --}}
            @if (count($placeholders) > 0)
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Available Placeholders</h3>
                    <p class="text-xs text-gray-500 mb-3">Click to copy. Use these in the subject or body.</p>
                    <div class="space-y-1.5">
                        @foreach ($placeholders as $placeholder)
                            <button type="button" onclick="copyPlaceholder(this, '{{ $placeholder }}')" class="w-full text-left px-3 py-1.5 rounded-md bg-gray-50 border border-gray-200 hover:bg-gray-100 transition-colors group cursor-pointer">
                                <span class="font-mono text-xs text-amber-700">{!! '{' . e($placeholder) . '}' !!}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
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
            relative_urls: false,
            remove_script_host: true,
        });
    </script>
    @endpush
</x-admin-layout>
