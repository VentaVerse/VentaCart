<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>
    @include('admin.extensions.seo.partials.tabs')

    <form method="POST" action="{{ route('admin.extensions.seo.save-settings') }}">
        @csrf

        {{-- ==================== API Connections ==================== --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">API Connections</h3>
            <p class="text-xs text-gray-500 mb-5">Connect Claude AI and Google Search Console to power SEO optimization.</p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-4xl">
                {{-- Claude --}}
                <div class="space-y-4">
                    <h4 class="text-sm font-medium text-gray-700 flex items-center gap-2">
                        Claude AI
                        @if ($maskedApiKey)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Connected</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Not configured</span>
                        @endif
                    </h4>
                    <div>
                        <label for="claude_api_key" class="block text-xs text-gray-500">API Key</label>
                        @if ($maskedApiKey)
                            <p class="text-xs text-gray-400 mb-1 font-mono">{{ $maskedApiKey }}</p>
                        @endif
                        <input type="password" name="claude_api_key" id="claude_api_key" placeholder="{{ $maskedApiKey ? 'Leave blank to keep current' : 'sk-ant-...' }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-400">From <a href="https://console.anthropic.com/" target="_blank" class="text-indigo-600 hover:underline">console.anthropic.com</a></p>
                    </div>
                    <div>
                        <button type="button" onclick="document.getElementById('test-claude-form').submit()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Test Connection
                        </button>
                    </div>
                    <div>
                        <label for="claude_model" class="block text-xs text-gray-500">Model</label>
                        <select name="claude_model" id="claude_model" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="claude-opus-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-opus-4-6' ? 'selected' : '' }}>Opus 4.6 — most capable</option>
                            <option value="claude-sonnet-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-6' || empty($seoSettings['claude_model']) ? 'selected' : '' }}>Sonnet 4.6 — recommended</option>
                            <option value="claude-sonnet-4-5-20250514" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-5-20250514' ? 'selected' : '' }}>Sonnet 4.5 — balanced</option>
                            <option value="claude-haiku-4-5-20251001" {{ ($seoSettings['claude_model'] ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' }}>Haiku 4.5 — fast, cheap</option>
                        </select>
                    </div>
                </div>


                {{-- GSC --}}
                <div class="space-y-4">
                    <h4 class="text-sm font-medium text-gray-700 flex items-center gap-2">
                        Google Search Console
                        @if ($gscCredentialsExist)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Connected</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Optional</span>
                        @endif
                    </h4>
                    <div>
                        <label class="block text-xs text-gray-500">Site URL</label>
                        <input type="text" name="gsc_site_url" placeholder="https://example.com" value="{{ $seoSettings['gsc_site_url'] ?? '' }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Service Account Credentials (JSON)</label>
                        @if ($gscCredentialsExist)
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-xs text-gray-600">gsc-credentials.json</span>
                            </div>
                        @endif
                        <button type="button" onclick="document.getElementById('upload-gsc-form').querySelector('input[type=file]').click()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors {{ $gscCredentialsExist ? 'bg-gray-50 text-gray-600 border-gray-300 hover:bg-gray-100' : 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                            {{ $gscCredentialsExist ? 'Replace' : 'Upload' }}
                        </button>
                    </div>
                    <div>
                        <button type="button" onclick="document.getElementById('test-gsc-form').submit()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Test Connection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== Optimization Rules ==================== --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Optimization Rules</h3>
            <p class="text-xs text-gray-500 mb-5">Control when the cron job should skip or re-optimize entities. These rules apply to automated (cron) runs only — manual "Optimize" buttons always work.</p>

            <div class="space-y-5 max-w-2xl">
                {{-- Skip rules --}}
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Skip Rules <span class="text-xs text-gray-400 font-normal">— when to leave entities alone</span></h4>
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="rule_skip_pending" value="0">
                            <input type="checkbox" name="rule_skip_pending" value="1" {{ ($seoSettings['rule_skip_pending'] ?? '1') === '1' ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm text-gray-700">Skip if a pending suggestion exists</span>
                                <p class="text-xs text-gray-400">Don't generate new suggestions for entities that haven't been reviewed yet.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="rule_skip_recently_accepted" value="0">
                            <input type="checkbox" name="rule_skip_recently_accepted" value="1" {{ ($seoSettings['rule_skip_recently_accepted'] ?? '1') === '1' ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm text-gray-700">Skip if recently optimized</span>
                                <p class="text-xs text-gray-400">Don't re-optimize entities that were accepted within the refresh period below.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="rule_skip_complete" value="0">
                            <input type="checkbox" name="rule_skip_complete" value="1" {{ ($seoSettings['rule_skip_complete'] ?? '0') === '1' ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm text-gray-700">Skip if meta fields are already filled</span>
                                <p class="text-xs text-gray-400">Don't touch entities that already have meta_title and meta_description, even if they were set manually.</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Process rules --}}
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Process Rules <span class="text-xs text-gray-400 font-normal">— when to always optimize</span></h4>
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="rule_process_empty" value="0">
                            <input type="checkbox" name="rule_process_empty" value="1" {{ ($seoSettings['rule_process_empty'] ?? '1') === '1' ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm text-gray-700">Always process entities with empty meta fields</span>
                                <p class="text-xs text-gray-400">Entities with no meta_title or meta_description are always optimized regardless of other rules.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="rule_process_rejected" value="0">
                            <input type="checkbox" name="rule_process_rejected" value="1" {{ ($seoSettings['rule_process_rejected'] ?? '1') === '1' ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm text-gray-700">Re-process rejected suggestions</span>
                                <p class="text-xs text-gray-400">If you rejected a suggestion, Claude will try again with a fresh take on the next run.</p>
                            </div>
                        </label>

                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 w-5"></div>
                            <div class="flex-1">
                                <label class="text-sm text-gray-700">Refresh cycle (days)</label>
                                <p class="text-xs text-gray-400 mb-1">Re-optimize accepted entities after this many days. Set to 0 to never re-optimize accepted items.</p>
                                <input type="number" name="rule_refresh_days" value="{{ $seoSettings['rule_refresh_days'] ?? '90' }}" min="0" max="365"
                                    class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <span class="text-xs text-gray-400 ml-1">days (0 = never, 90 = quarterly)</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Scope --}}
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Scope <span class="text-xs text-gray-400 font-normal">— which entity types to include in cron runs</span></h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog_posts' => 'Blog Posts', 'pages' => 'Pages'] as $key => $label)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="rule_scope_{{ $key }}" value="0">
                                <input type="checkbox" name="rule_scope_{{ $key }}" value="1" {{ ($seoSettings['rule_scope_' . $key] ?? '1') === '1' ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Batch --}}
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Batch Limit <span class="text-xs text-gray-400 font-normal">— max entities per cron run</span></h4>
                    <div>
                        <input type="number" name="rule_batch_limit" value="{{ $seoSettings['rule_batch_limit'] ?? '0' }}" min="0"
                            class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <span class="text-xs text-gray-400 ml-1">per entity type (0 = unlimited)</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== General ==================== --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">General</h3>
            <p class="text-xs text-gray-500 mb-5">Other SEO behavior settings.</p>

            <div class="space-y-4 max-w-lg">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="auto_accept" value="0">
                    <input type="checkbox" name="auto_accept" value="1" {{ ($seoSettings['auto_accept'] ?? '0') === '1' ? 'checked' : '' }}
                        class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <span class="text-sm text-gray-700">Auto-accept suggestions</span>
                        <p class="text-xs text-gray-400">When enabled, generated suggestions are applied immediately without manual review. Use with caution.</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Save --}}
        <div class="flex justify-end mb-6">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Save Settings
            </button>
        </div>
    </form>

    {{-- Hidden forms (outside main form) --}}
    <form id="test-claude-form" method="POST" action="{{ route('admin.extensions.seo.test-claude') }}" class="hidden">@csrf</form>
    <form id="test-gsc-form" method="POST" action="{{ route('admin.extensions.seo.test-gsc') }}" class="hidden">@csrf</form>
    <form id="upload-gsc-form" method="POST" action="{{ route('admin.extensions.seo.upload-gsc') }}" enctype="multipart/form-data" class="hidden">
        @csrf
        <input type="file" name="gsc_credentials" accept=".json" onchange="this.form.submit()">
    </form>

    {{-- ==================== Cron Job ==================== --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Cron Job</h3>
        <div class="max-w-2xl" x-data="{ copied: false }">
            <p class="text-xs text-gray-500 mb-3">Add this command to your hosting panel's cron scheduler. The rules above control what gets processed each run.</p>
            <div class="flex items-center gap-2">
                <code class="block flex-1 bg-gray-100 rounded-md px-4 py-3 text-sm text-gray-800 font-mono select-all border border-gray-200">/usr/bin/php {{ base_path() }}/artisan seo:generate >> /dev/null 2>&1</code>
                <button type="button"
                    @click="navigator.clipboard.writeText('/usr/bin/php {{ base_path() }}/artisan seo:generate >> /dev/null 2>&1'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 border border-gray-300 transition-colors"
                    x-text="copied ? 'Copied!' : 'Copy'">
                    Copy
                </button>
            </div>
        </div>
    </div>
</x-admin-layout>
