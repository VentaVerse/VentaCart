<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>
    @include('admin.extensions.seo.partials.tabs')

    {{-- Claude API Settings --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Claude API Settings</h3>

        <form method="POST" action="{{ route('admin.extensions.seo.save-settings') }}">
            @csrf
            <div class="space-y-4 max-w-lg">
                {{-- Claude API Key --}}
                <div>
                    <label for="claude_api_key" class="block text-sm font-medium text-gray-700">Claude API Key</label>
                    @if ($maskedApiKey)
                        <p class="text-xs text-gray-500 mt-0.5">Current key: <span class="font-mono">{{ $maskedApiKey }}</span></p>
                    @endif
                    <input type="password" name="claude_api_key" id="claude_api_key" placeholder="sk-ant-..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500">Get your API key from <a href="https://console.anthropic.com/" target="_blank" class="text-indigo-600 hover:text-indigo-500 underline">console.anthropic.com</a></p>
                </div>

                {{-- Claude Model --}}
                <div>
                    <label for="claude_model" class="block text-sm font-medium text-gray-700">Claude Model</label>
                    <select name="claude_model" id="claude_model"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="claude-opus-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-opus-4-6' ? 'selected' : '' }}>Claude Opus 4.6 — most capable</option>
                        <option value="claude-sonnet-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-6' || empty($seoSettings['claude_model']) ? 'selected' : '' }}>Claude Sonnet 4.6 — advanced (recommended)</option>
                        <option value="claude-sonnet-4-5-20250514" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-5-20250514' ? 'selected' : '' }}>Claude Sonnet 4.5 — balanced</option>
                        <option value="claude-haiku-4-5-20251001" {{ ($seoSettings['claude_model'] ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' }}>Claude Haiku 4.5 — fast, cost-effective</option>
                    </select>
                </div>

                {{-- Auto-accept --}}
                <div class="flex items-center gap-3">
                    <input type="hidden" name="auto_accept" value="0">
                    <input type="checkbox" name="auto_accept" id="auto_accept" value="1"
                        {{ ($seoSettings['auto_accept'] ?? '0') === '1' ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <label for="auto_accept" class="text-sm font-medium text-gray-700">Auto-accept suggestions</label>
                </div>

                <div class="pt-2 flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        Save Settings
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-4 pt-4 border-t border-gray-100 max-w-lg">
            <form method="POST" action="{{ route('admin.extensions.seo.test-claude') }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Test Claude Connection
                </button>
            </form>
        </div>
    </div>

    {{-- Google Search Console --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Google Search Console</h3>

        {{-- GSC Credentials Upload --}}
        <form method="POST" action="{{ route('admin.extensions.seo.upload-gsc') }}" enctype="multipart/form-data">
            @csrf
            <div class="space-y-4 max-w-lg">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Account Credentials</label>
                    @if ($gscCredentialsExist)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mb-2">Uploaded</span>
                    @endif
                    <input type="file" name="gsc_credentials" accept=".json"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        Upload
                    </button>
                </div>
            </div>
        </form>

        <hr class="my-6 border-gray-200">

        {{-- GSC Site URL --}}
        <form method="POST" action="{{ route('admin.extensions.seo.save-settings') }}">
            @csrf
            <div class="space-y-4 max-w-lg">
                <div>
                    <label for="gsc_site_url" class="block text-sm font-medium text-gray-700">GSC Site URL</label>
                    <input type="text" name="gsc_site_url" id="gsc_site_url" placeholder="https://example.com"
                        value="{{ $seoSettings['gsc_site_url'] ?? '' }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        Save
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-4 pt-4 border-t border-gray-100 max-w-lg">
            <form method="POST" action="{{ route('admin.extensions.seo.test-gsc') }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Test GSC Connection
                </button>
            </form>
        </div>
    </div>

    {{-- Cron Job --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Cron Job</h3>
        <div class="max-w-lg" x-data="{ copied: false }">
            <div class="flex items-center gap-2">
                <code class="block flex-1 bg-gray-100 rounded-md px-4 py-3 text-sm text-gray-800 font-mono select-all border border-gray-200">/usr/bin/php {{ base_path() }}/artisan seo:generate >> /dev/null 2>&1</code>
                <button type="button"
                    @click="navigator.clipboard.writeText('/usr/bin/php {{ base_path() }}/artisan seo:generate >> /dev/null 2>&1'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 border border-gray-300 transition-colors"
                    x-text="copied ? 'Copied!' : 'Copy'">
                    Copy
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">Add this command to your hosting panel's cron scheduler. Recommended: monthly on the 1st.</p>
        </div>
    </div>

</x-admin-layout>
