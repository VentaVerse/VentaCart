<x-admin-layout>
    <x-slot name="header">Settings</x-slot>

    @php
        $tabs = [
            ['id' => 'general', 'label' => 'General'],
            ['id' => 'currency', 'label' => 'Currency'],
            ['id' => 'checkout', 'label' => 'Checkout'],
            ['id' => 'mail', 'label' => 'Mail'],
            ['id' => 'api', 'label' => 'API'],
            ['id' => 'security', 'label' => 'Security'],
            ['id' => 'automations', 'label' => 'Automations'],
        ];
        $validTabIds = array_map(fn ($t) => $t['id'], $tabs);
        $initialTab = request('tab', session('tab', 'general'));
        if (!in_array($initialTab, $validTabIds, true)) {
            $initialTab = 'general';
        }
    @endphp

    <div x-data="{
            activeTab: '{{ $initialTab }}',
            validTabs: @js($validTabIds),
            setTab(id) {
                if (!this.validTabs.includes(id) || this.activeTab === id) return;
                this.activeTab = id;
                // Keep URL in sync so refresh / save / deep-linking all land on the same tab.
                // replaceState (not pushState) — tabs are view state, not history entries.
                const url = new URL(window.location.href);
                url.searchParams.set('tab', id);
                window.history.replaceState({ tab: id }, '', url.toString());
            },
            init() {
                // Browser back/forward: if user navigates history, sync activeTab to the URL.
                window.addEventListener('popstate', () => {
                    const tab = new URL(window.location.href).searchParams.get('tab');
                    if (tab && this.validTabs.includes(tab)) {
                        this.activeTab = tab;
                    }
                });
                // Ensure URL reflects the initial tab on first load (even when landing without ?tab=).
                if (!new URL(window.location.href).searchParams.has('tab')) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', this.activeTab);
                    window.history.replaceState({ tab: this.activeTab }, '', url.toString());
                }
            }
        }">
        {{-- Tab Navigation --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @foreach ($tabs as $t)
                    <button type="button" @click="setTab('{{ $t['id'] }}')"
                        :class="activeTab === '{{ $t['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">{{ $t['label'] }}</button>
                @endforeach
            </nav>

            {{-- ==================== GENERAL ==================== --}}
            <div x-show="activeTab === 'general'" class="p-6" x-data="{
                saveGeneral() {
                    // Save identity settings
                    const identityForm = new FormData();
                    identityForm.append('_token', '{{ csrf_token() }}');
                    identityForm.append('group', 'identity');
                    identityForm.append('settings[site_name]', this.$refs.siteName.value);
                    fetch('{{ route('admin.settings.update') }}', { method: 'POST', body: identityForm });

                    // Submit the main form (timezone under general)
                    this.$refs.generalForm.submit();
                }
            }">
                <form method="POST" action="{{ route('admin.settings.update') }}" x-ref="generalForm">
                    @csrf
                    <input type="hidden" name="group" value="general">
                    <input type="hidden" name="_tab" value="general">

                    {{-- Site Name --}}
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Store Identity</h3>
                    <div class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Site Name</label>
                            <input type="text" x-ref="siteName" value="{{ $settings['identity']['site_name'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                    </div>

                    {{-- Logo / Favicon (previews only — forms are outside the parent form below) --}}
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-lg">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Logo</h4>
                            @if (!empty($settings['identity']['logo']))
                                <div class="flex items-center gap-3 mb-2">
                                    <img src="{{ storage_url($settings['identity']['logo']) }}" class="h-12" alt="Logo">
                                    <button type="button" onclick="document.getElementById('remove-logo-form').submit()" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                </div>
                            @endif
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Favicon</h4>
                            @if (!empty($settings['identity']['favicon']))
                                <div class="flex items-center gap-3 mb-2">
                                    <img src="{{ storage_url($settings['identity']['favicon']) }}" class="h-8" alt="Favicon">
                                    <button type="button" onclick="document.getElementById('remove-favicon-form').submit()" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-2 mt-3 max-w-lg">
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 cursor-pointer border border-gray-300 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            {{ !empty($settings['identity']['logo']) ? 'Change Logo' : 'Upload Logo' }}
                            <input type="file" accept="image/*" onchange="document.getElementById('upload-logo-form').querySelector('input[name=logo]').files = this.files; document.getElementById('upload-logo-form').submit();" class="sr-only">
                        </label>
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 cursor-pointer border border-gray-300 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            {{ !empty($settings['identity']['favicon']) ? 'Change Favicon' : 'Upload Favicon' }}
                            <input type="file" accept=".ico,.png,.svg" onchange="document.getElementById('upload-favicon-form').querySelector('input[name=favicon]').files = this.files; document.getElementById('upload-favicon-form').submit();" class="sr-only">
                        </label>
                    </div>

                    {{-- Timezone --}}
                    <div class="border-t border-gray-200 mt-8 pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Timezone</h3>
                        <div class="max-w-lg">
                            <select name="settings[timezone]" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                @php $currentTz = $settings['general']['timezone'] ?? config('app.timezone', 'Asia/Manila'); @endphp
                                @foreach (timezone_identifiers_list() as $tz)
                                    <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- SEO --}}
                    <div class="border-t border-gray-200 mt-8 pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">SEO</h3>
                        <div class="space-y-4 max-w-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Default Meta Title</label>
                                <input type="text" name="settings[default_meta_title]" value="{{ $settings['general']['default_meta_title'] ?? '' }}" placeholder="{{ $settings['identity']['site_name'] ?? 'My Store' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-400">Used as the homepage title and fallback for pages without a specific meta title.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Default Meta Description</label>
                                <textarea name="settings[default_meta_description]" rows="2" placeholder="Describe your store in 155 characters..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ $settings['general']['default_meta_description'] ?? '' }}</textarea>
                                <p class="mt-1 text-xs text-gray-400">Shown in search results for your homepage. Keep under 155 characters.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Meta Keywords</label>
                                <input type="text" name="settings[default_meta_keywords]" value="{{ $settings['general']['default_meta_keywords'] ?? '' }}" placeholder="guitar pedals, music gear, Philippines" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-400">Comma-separated. Low impact on modern SEO but used by some search engines.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Google Site Verification</label>
                                <input type="text" name="settings[google_site_verification]" value="{{ $settings['general']['google_site_verification'] ?? '' }}" placeholder="google-site-verification=..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono">
                                <p class="mt-1 text-xs text-gray-400">The content value from your Google Search Console verification meta tag.</p>
                            </div>
                        </div>

                    </div>

                    {{-- Save --}}
                    <div class="mt-6 flex justify-end">
                        <button type="button" @click="saveGeneral()" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                    </div>
                </form>

                {{-- Logo/Favicon forms (must be outside the parent form) --}}
                <form id="remove-logo-form" method="POST" action="{{ route('admin.theme.remove-logo') }}" class="hidden">@csrf @method('DELETE')</form>
                <form id="remove-favicon-form" method="POST" action="{{ route('admin.theme.remove-favicon') }}" class="hidden">@csrf @method('DELETE')</form>
                <form id="upload-logo-form" method="POST" action="{{ route('admin.theme.upload-logo') }}" enctype="multipart/form-data" class="hidden">@csrf <input type="file" name="logo"></form>
                <form id="upload-favicon-form" method="POST" action="{{ route('admin.theme.upload-favicon') }}" enctype="multipart/form-data" class="hidden">@csrf <input type="file" name="favicon"></form>

            </div>

            {{-- ==================== CURRENCY ==================== --}}
            <div x-show="activeTab === 'currency'" class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Currencies</h3>
                        <form method="POST" action="{{ route('admin.theme.currencies.fetch-rates') }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700">Fetch ECB Rates</button>
                        </form>
                    </div>

                    <div class="space-y-2">
                        @foreach ($currencies as $cur)
                            <div class="flex items-center gap-4 bg-gray-50 rounded-lg p-3 {{ $cur->is_enabled ? '' : 'opacity-60' }}">
                                <span class="font-mono text-sm font-bold w-10">{{ $cur->code }}</span>
                                {{-- Inline edit: name, symbol, rate --}}
                                <form method="POST" action="{{ route('admin.theme.currencies.update', $cur) }}" class="flex items-center gap-2 flex-1">
                                    @csrf @method('PUT')
                                    {{-- Preserve enabled state: the controller reads is_enabled on every update. --}}
                                    <input type="hidden" name="is_enabled" value="{{ $cur->is_enabled ? 1 : 0 }}">
                                    <input type="text" name="name" value="{{ $cur->name }}" required maxlength="255"
                                           class="flex-1 min-w-0 rounded border-gray-300 text-sm py-1" placeholder="Name">
                                    <input type="text" name="symbol" value="{{ $cur->symbol }}" required maxlength="10"
                                           class="w-14 rounded border-gray-300 text-sm py-1 text-center" placeholder="₱">
                                    <input type="number" name="rate" value="{{ $cur->rate }}" step="0.000001" min="0.000001" required
                                           @if ($cur->is_default) readonly @endif
                                           class="w-32 rounded border-gray-300 text-sm py-1 {{ $cur->is_default ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : '' }}"
                                           title="{{ $cur->is_default ? 'Base currency — its rate is always 1.' : 'Units of '.$cur->code.' per 1 '.($defaultCurrency?->code ?? 'base') }}">
                                    <button type="submit" class="px-3 py-1 bg-indigo-600 text-white text-xs font-medium rounded hover:bg-indigo-700 shrink-0">Save</button>
                                </form>
                                @if ($cur->is_default)
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">Default</span>
                                    <span class="text-xs text-gray-400" title="The default currency is the price base and can't be turned off.">Always on</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded {{ $cur->is_enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $cur->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                    <form method="POST" action="{{ route('admin.theme.currencies.update', $cur) }}" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="name" value="{{ $cur->name }}">
                                        <input type="hidden" name="symbol" value="{{ $cur->symbol }}">
                                        <input type="hidden" name="rate" value="{{ $cur->rate }}">
                                        <input type="hidden" name="is_enabled" value="{{ $cur->is_enabled ? 0 : 1 }}">
                                        <button type="submit" class="text-sm {{ $cur->is_enabled ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }}">
                                            {{ $cur->is_enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.theme.currencies.update', $cur) }}" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="name" value="{{ $cur->name }}">
                                        <input type="hidden" name="symbol" value="{{ $cur->symbol }}">
                                        <input type="hidden" name="rate" value="{{ $cur->rate }}">
                                        <input type="hidden" name="is_default" value="1">
                                        <input type="hidden" name="is_enabled" value="1">
                                        <button type="submit" class="text-indigo-600 hover:text-indigo-800 text-sm">Set Default</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.theme.currencies.destroy', $cur) }}" class="inline" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm">Delete</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Add Currency</h4>
                        <form method="POST" action="{{ route('admin.theme.currencies.store') }}" class="grid grid-cols-5 gap-3 max-w-2xl items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Code</label>
                                <input type="text" name="code" maxlength="3" required class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm uppercase" placeholder="USD">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Name</label>
                                <input type="text" name="name" required class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="US Dollar">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Symbol</label>
                                <input type="text" name="symbol" maxlength="10" required class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="$">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Rate</label>
                                <input type="number" name="rate" step="0.000001" required class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="0.018">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ==================== CHECKOUT ==================== --}}
            <div x-show="activeTab === 'checkout'" class="p-6" x-data="{
                saveCheckout() {
                    const stockForm = new FormData();
                    stockForm.append('_token', '{{ csrf_token() }}');
                    stockForm.append('group', 'stock');
                    stockForm.append('settings[low_stock_alert_enabled]', this.$refs.lowStockEnabled.checked ? '1' : '0');
                    stockForm.append('settings[low_stock_threshold]', this.$refs.lowStockThreshold.value);
                    stockForm.append('settings[low_stock_alert_email]', this.$refs.lowStockEmail.value);
                    fetch('{{ route('admin.settings.update') }}', { method: 'POST', body: stockForm });
                    this.$refs.checkoutForm.submit();
                }
            }">
                <form method="POST" action="{{ route('admin.settings.update') }}" x-ref="checkoutForm">
                    @csrf
                    <input type="hidden" name="group" value="checkout">
                    <input type="hidden" name="_tab" value="checkout">

                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Checkout</h3>
                    <div class="space-y-4 max-w-lg">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[guest_allowed]" value="0">
                            <input type="checkbox" name="settings[guest_allowed]" value="1" {{ ($settings['checkout']['guest_allowed'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Allow Guest Checkout</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[block_out_of_stock]" value="0">
                            <input type="checkbox" name="settings[block_out_of_stock]" value="1" {{ ($settings['checkout']['block_out_of_stock'] ?? '1') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Block checkout for out-of-stock items</label>
                        </div>
                        <p class="text-xs text-gray-500 -mt-2 ml-7">Prevents orders when cart contains products or variants with zero stock. Items added before going out of stock will be blocked at checkout.</p>
                    </div>

                    {{-- Low Stock Alerts --}}
                    <div class="border-t border-gray-200 mt-8 pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Low Stock Alerts</h3>
                        <div class="space-y-4 max-w-lg">
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="_stock_low_stock_alert_enabled" value="0">
                                <input type="checkbox" x-ref="lowStockEnabled" value="1"
                                    {{ ($settings['stock']['low_stock_alert_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600">
                                <label class="text-sm text-gray-700">Enable low stock email alerts</label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stock Threshold</label>
                                <p class="text-xs text-gray-500 mb-1">Alert when a product's stock drops to or below this number.</p>
                                <input type="number" x-ref="lowStockThreshold"
                                    value="{{ $settings['stock']['low_stock_threshold'] ?? 5 }}"
                                    min="1" max="100"
                                    class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Send alerts to</label>
                                <input type="email" x-ref="lowStockEmail"
                                    value="{{ $settings['stock']['low_stock_alert_email'] ?? '' }}"
                                    placeholder="{{ auth()->user()->email }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p class="text-xs text-gray-500 mt-1">Leave blank to use admin email.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" @click="saveCheckout()" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                    </div>
                </form>
            </div>

            {{-- ==================== MAIL ==================== --}}
            <div x-show="activeTab === 'mail'" class="p-6" x-data="{ mailDriver: '{{ $settings['mail']['driver'] ?? 'smtp' }}' }">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="mail">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Mail Configuration</h3>
                    <div class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mail Driver</label>
                            <select name="settings[driver]" x-model="mailDriver" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="smtp">SMTP</option>
                                <option value="mail">PHP Mail</option>
                            </select>
                        </div>
                        <template x-if="mailDriver === 'smtp'">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">SMTP Host</label>
                                    <input type="text" name="settings[host]" value="{{ $settings['mail']['host'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="smtp.gmail.com">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">SMTP Port</label>
                                    <input type="number" name="settings[port]" value="{{ $settings['mail']['port'] ?? '587' }}" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">SMTP Username</label>
                                    <input type="text" name="settings[username]" value="{{ $settings['mail']['username'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">SMTP Password</label>
                                    <input type="password" name="settings[password]" value="{{ $settings['mail']['password'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Encryption</label>
                                    <select name="settings[encryption]" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="tls" {{ ($settings['mail']['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                                        <option value="ssl" {{ ($settings['mail']['encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                                        <option value="" {{ ($settings['mail']['encryption'] ?? 'tls') === '' ? 'selected' : '' }}>None</option>
                                    </select>
                                </div>
                            </div>
                        </template>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">From Address</label>
                            <input type="email" name="settings[from_address]" value="{{ $settings['mail']['from_address'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="noreply@example.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">From Name</label>
                            <input type="text" name="settings[from_name]" value="{{ $settings['mail']['from_name'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Your Store">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                    </div>
                </form>

                {{-- Test Email --}}
                <div class="border-t border-gray-200 mt-8 pt-8">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Test Email</h3>
                    <form method="POST" action="{{ route('admin.settings.test-mail') }}" class="flex items-end gap-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Recipient</label>
                            <input type="email" name="to" value="{{ auth()->user()->email }}" required class="block w-64 rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Send Test</button>
                    </form>
                </div>
            </div>

            {{-- ==================== API ==================== --}}
            <div x-show="activeTab === 'api'" x-cloak class="p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">ERP API Token</h3>
                <p class="text-sm text-gray-500 mb-4">This token is used by the ERP system to authenticate API requests. Keep it secret.</p>

                @php
                    $erpToken = $settings['api']['erp_token'] ?? null;
                    $newToken = session('new_erp_token');
                @endphp

                @if ($newToken)
                    <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg" x-data="{ copied: false }">
                        <p class="text-sm font-medium text-amber-800 mb-2">New token generated — copy it now. It will not be shown again in full.</p>
                        <div class="flex items-center gap-2">
                            <input type="text" x-ref="tokenInput" value="{{ $newToken }}" readonly
                                class="flex-1 font-mono text-sm bg-white border border-amber-300 rounded-md px-3 py-2 text-gray-800">
                            <button type="button" @click="
                                $refs.tokenInput.select();
                                document.execCommand('copy');
                                window.getSelection().removeAllRanges();
                                if (navigator.clipboard) { navigator.clipboard.writeText($refs.tokenInput.value); }
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            " class="px-3 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 whitespace-nowrap"
                                x-text="copied ? 'Copied!' : 'Copy'">Copy</button>
                        </div>
                    </div>
                @elseif ($erpToken)
                    <div class="mb-4" x-data="{ copied: false }">
                        <div class="flex items-center gap-2">
                            <input type="text" value="{{ str_repeat('*', max(0, strlen($erpToken) - 8)) . substr($erpToken, -8) }}" readonly
                                class="flex-1 font-mono text-sm bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-gray-500">
                            <input type="hidden" x-ref="tokenHidden" value="{{ $erpToken }}">
                            <button type="button" @click="
                                const tmp = document.createElement('textarea');
                                tmp.value = $refs.tokenHidden.value;
                                tmp.style.position = 'fixed';
                                tmp.style.opacity = '0';
                                document.body.appendChild(tmp);
                                tmp.select();
                                document.execCommand('copy');
                                document.body.removeChild(tmp);
                                if (navigator.clipboard) { navigator.clipboard.writeText($refs.tokenHidden.value); }
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            " class="px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 whitespace-nowrap"
                                x-text="copied ? 'Copied!' : 'Copy'">Copy</button>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-400 mb-4">No token generated yet.</p>
                @endif

                <form method="POST" action="{{ route('admin.settings.generate-erp-token') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                        onclick="return confirm('{{ $erpToken ? 'This will invalidate the current token. Continue?' : 'Generate a new ERP API token?' }}')">
                        {{ $erpToken ? 'Regenerate Token' : 'Generate Token' }}
                    </button>
                </form>

                <div class="mt-8 border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">API Endpoints</h3>
                    <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs space-y-1 text-gray-600">
                        @php
                            $apiRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes())
                                ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1'))
                                ->sortBy(fn ($r) => $r->uri())
                                ->map(fn ($r) => ['methods' => collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->values()->all(), 'uri' => '/' . $r->uri()]);
                            $methodColors = ['GET' => 'text-green-600', 'POST' => 'text-blue-600', 'PUT' => 'text-amber-600', 'PATCH' => 'text-amber-600', 'DELETE' => 'text-red-600'];
                        @endphp
                        @foreach ($apiRoutes as $route)
                            @foreach ($route['methods'] as $method)
                                <p><span class="{{ $methodColors[$method] ?? 'text-gray-600' }} font-semibold">{{ $method }}</span> {{ $route['uri'] }}</p>
                            @endforeach
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-400">All requests require <code class="bg-gray-100 px-1 rounded">Authorization: Bearer {token}</code> header.</p>
                </div>

                <div class="mt-8 border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Sync Logging</h3>
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="_redirect" value="{{ route('admin.settings.index', ['tab' => 'api']) }}">
                        <input type="hidden" name="group" value="api">
                        <div class="flex items-center gap-2 mb-4">
                            <input type="hidden" name="settings[api_sync_log_enabled]" value="0">
                            <input type="checkbox" name="settings[api_sync_log_enabled]" id="api_sync_log_enabled" value="1"
                                {{ ($settings['api']['api_sync_log_enabled'] ?? '1') === '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="api_sync_log_enabled" class="text-sm text-gray-700">Log API sync requests to database</label>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Save</button>
                    </form>
                </div>

                <div class="mt-6 flex justify-end">
                    <a href="{{ route('admin.api-sync-logs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        View API Sync Logs &rarr;
                    </a>
                </div>

            </div>

            {{-- ==================== SECURITY ==================== --}}
            <div x-show="activeTab === 'security'" x-cloak class="p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Admin URL</h3>
                <p class="text-xs text-gray-500 mb-4">Change the admin panel URL path for security. Default is <code class="bg-gray-100 px-1 rounded">/admin</code>.</p>

                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="security">
                    <input type="hidden" name="_tab" value="security">
                    <div class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Admin Path</label>
                            <div class="mt-1 flex items-center gap-1">
                                <span class="text-sm text-gray-500">/</span>
                                <input type="text" name="settings[admin_prefix]" value="{{ $settings['security']['admin_prefix'] ?? 'admin' }}" class="block w-full rounded-md border-gray-300 text-sm font-mono" placeholder="admin" pattern="[a-z][a-z0-9\-]*" required>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Lowercase letters, numbers, and hyphens only. Must start with a letter.</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                    </div>
                </form>

                <div class="mt-8 border-t border-gray-200 pt-6"></div>

                <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Cloudflare Turnstile</h3>
                <p class="text-xs text-gray-500 mb-4">Protects login, registration, contact, and stock notification forms from bots. Get your keys at <a href="https://dash.cloudflare.com/turnstile" target="_blank" class="text-indigo-600 hover:underline">dash.cloudflare.com/turnstile</a>.</p>

                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="security">
                    <input type="hidden" name="_tab" value="security">
                    <div class="space-y-4 max-w-lg">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="hidden" name="settings[turnstile_enabled]" value="0">
                            <input type="checkbox" name="settings[turnstile_enabled]" value="1" {{ ($settings['security']['turnstile_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            Enable Turnstile
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Site Key</label>
                            <input type="text" name="settings[turnstile_site_key]" value="{{ $settings['security']['turnstile_site_key'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono" placeholder="0x4AAAAAAA...">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Secret Key</label>
                            @if (!empty($maskedKeys['turnstile_secret_key'] ?? ''))
                                <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['turnstile_secret_key'] }}</p>
                            @endif
                            <input type="password" name="settings[turnstile_secret_key]" autocomplete="new-password" placeholder="{{ !empty($maskedKeys['turnstile_secret_key'] ?? '') ? 'Leave blank to keep current' : 'Enter secret key' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                    </div>
                </form>

                <div class="mt-8 border-t border-gray-200 pt-6">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Protected Forms</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>Customer Login</li>
                        <li>Customer Registration</li>
                        <li>Forgot Password</li>
                        <li>Contact Form</li>
                        <li>Stock Notification (Notify Me)</li>
                    </ul>
                </div>
            </div>

            {{-- ==================== AUTOMATIONS ==================== --}}
            <div x-show="activeTab === 'automations'" x-cloak>
                @include('admin.settings._automations_tab', ['automations' => $automations])
            </div>

        </div>
    </div>
</x-admin-layout>
