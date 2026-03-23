<x-admin-layout>
    <x-slot name="header">Settings</x-slot>

    <div x-data="{ activeTab: '{{ session('tab', 'general') }}' }">
        {{-- Tab Navigation --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @php
                    $tabs = [
                        ['id' => 'general', 'label' => 'General'],
                        ['id' => 'currency', 'label' => 'Currency'],
                        ['id' => 'checkout', 'label' => 'Checkout'],
                        ['id' => 'mail', 'label' => 'Mail'],
                        ['id' => 'api', 'label' => 'API'],
                        ['id' => 'cron', 'label' => 'Cron Jobs'],
                    ];
                @endphp
                @foreach ($tabs as $t)
                    <button type="button" @click="activeTab = '{{ $t['id'] }}'"
                        :class="activeTab === '{{ $t['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">{{ $t['label'] }}</button>
                @endforeach
            </nav>

            {{-- ==================== GENERAL ==================== --}}
            <div x-show="activeTab === 'general'" class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="identity">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Store Identity</h3>
                    <div class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Site Name</label>
                            <input type="text" name="settings[site_name]" value="{{ $settings['identity']['site_name'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Identity</button>
                    </div>
                </form>

                <div class="mt-8 border-t pt-6 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-lg">
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Logo</h4>
                        @if (!empty($settings['identity']['logo']))
                            <img src="{{ asset('storage/' . $settings['identity']['logo']) }}" class="h-12 mb-2" alt="Logo">
                        @endif
                        <form method="POST" action="{{ route('admin.theme.upload-logo') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                            @csrf
                            <input type="file" name="logo" accept="image/*" class="text-sm">
                            <button type="submit" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded hover:bg-gray-300">Upload</button>
                        </form>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Favicon</h4>
                        @if (!empty($settings['identity']['favicon']))
                            <img src="{{ asset('storage/' . $settings['identity']['favicon']) }}" class="h-8 mb-2" alt="Favicon">
                        @endif
                        <form method="POST" action="{{ route('admin.theme.upload-favicon') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                            @csrf
                            <input type="file" name="favicon" accept=".ico,.png,.svg" class="text-sm">
                            <button type="submit" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded hover:bg-gray-300">Upload</button>
                        </form>
                    </div>
                </div>

                {{-- Timezone --}}
                <div class="border-t border-gray-200 mt-8 pt-8">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="general">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Timezone</h3>
                        <div class="max-w-lg">
                            <select name="settings[timezone]" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                @php $currentTz = $settings['general']['timezone'] ?? config('app.timezone', 'Asia/Manila'); @endphp
                                @foreach (timezone_identifiers_list() as $tz)
                                    <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Timezone</button>
                        </div>
                    </form>
                </div>


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
                            <div class="flex items-center gap-4 bg-gray-50 rounded-lg p-3">
                                <span class="font-mono text-sm font-bold w-10">{{ $cur->code }}</span>
                                <span class="text-sm flex-1">{{ $cur->name }}</span>
                                <span class="text-sm text-gray-500">{{ $cur->symbol }}</span>
                                <span class="text-sm text-gray-500">Rate: {{ $cur->rate }}</span>
                                @if ($cur->is_default)
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">Default</span>
                                @else
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
            <div x-show="activeTab === 'checkout'" class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="checkout">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Checkout</h3>
                    <div class="space-y-4 max-w-lg">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[guest_allowed]" value="0">
                            <input type="checkbox" name="settings[guest_allowed]" value="1" {{ ($settings['checkout']['guest_allowed'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Allow Guest Checkout</label>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Checkout Settings</button>
                    </div>
                </form>

                {{-- Stock management moved to Sales > Order Statuses --}}

                {{-- Low Stock Alerts --}}
                <div class="border-t border-gray-200 mt-8 pt-8">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="stock">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Low Stock Alerts</h3>
                        <div class="space-y-4 max-w-lg">
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="settings[low_stock_alert_enabled]" value="0">
                                <input type="checkbox" name="settings[low_stock_alert_enabled]" value="1"
                                    {{ ($settings['stock']['low_stock_alert_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600">
                                <label class="text-sm text-gray-700">Enable low stock email alerts</label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stock Threshold</label>
                                <p class="text-xs text-gray-500 mb-1">Alert when a product's stock drops to or below this number.</p>
                                <input type="number" name="settings[low_stock_threshold]"
                                    value="{{ $settings['stock']['low_stock_threshold'] ?? 5 }}"
                                    min="1" max="100"
                                    class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Send alerts to</label>
                                <input type="email" name="settings[low_stock_alert_email]"
                                    value="{{ $settings['stock']['low_stock_alert_email'] ?? '' }}"
                                    placeholder="{{ auth()->user()->email }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p class="text-xs text-gray-500 mt-1">Leave blank to use admin email.</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Low Stock Settings</button>
                        </div>
                    </form>
                </div>
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
                            <input type="email" name="settings[from_address]" value="{{ $settings['mail']['from_address'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="noreply@newgearday.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">From Name</label>
                            <input type="text" name="settings[from_name]" value="{{ $settings['mail']['from_name'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="New Gear Day">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Mail Settings</button>
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
                    <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <p class="text-sm font-medium text-amber-800 mb-2">New token generated — copy it now. It will not be shown again in full.</p>
                        <div class="flex items-center gap-2">
                            <input type="text" id="erp-token-full" value="{{ $newToken }}" readonly
                                class="flex-1 font-mono text-sm bg-white border border-amber-300 rounded-md px-3 py-2 text-gray-800">
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('erp-token-full').value).then(() => this.textContent = 'Copied!')"
                                class="px-3 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 whitespace-nowrap">Copy</button>
                        </div>
                    </div>
                @elseif ($erpToken)
                    <div class="mb-4">
                        <div class="flex items-center gap-2">
                            <input type="text" value="{{ str_repeat('*', max(0, strlen($erpToken) - 8)) . substr($erpToken, -8) }}" readonly
                                class="flex-1 font-mono text-sm bg-gray-50 border border-gray-300 rounded-md px-3 py-2 text-gray-500">
                            <button type="button" onclick="navigator.clipboard.writeText('{{ $erpToken }}').then(() => this.textContent = 'Copied!')"
                                class="px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 whitespace-nowrap">Copy</button>
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
                        <p><span class="text-green-600 font-semibold">GET</span> /api/erp/products</p>
                        <p><span class="text-green-600 font-semibold">GET</span> /api/erp/products/{sku}</p>
                        <p><span class="text-blue-600 font-semibold">POST</span> /api/erp/products</p>
                        <p><span class="text-amber-600 font-semibold">PUT</span> /api/erp/products/{sku}</p>
                        <p><span class="text-blue-600 font-semibold">POST</span> /api/erp/products/{sku}/images</p>
                        <p><span class="text-green-600 font-semibold">GET</span> /api/erp/variants/{sku}</p>
                        <p><span class="text-amber-600 font-semibold">PUT</span> /api/erp/variants/{sku}</p>
                        <p><span class="text-green-600 font-semibold">GET</span> /api/erp/orders</p>
                        <p><span class="text-green-600 font-semibold">GET</span> /api/erp/orders/{id}</p>
                        <p><span class="text-amber-600 font-semibold">PUT</span> /api/erp/orders/{id}/status</p>
                    </div>
                    <p class="mt-3 text-xs text-gray-400">All requests require <code class="bg-gray-100 px-1 rounded">Authorization: Bearer {token}</code> header.</p>
                </div>

                <div class="mt-6">
                    <a href="{{ route('admin.api-sync-logs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        View API Sync Logs &rarr;
                    </a>
                </div>
            </div>

            {{-- ==================== CRON JOBS ==================== --}}
            <div x-show="activeTab === 'cron'" x-cloak class="p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Scheduled Tasks</h3>
                <p class="text-xs text-gray-500 mb-4">These tasks run automatically via Laravel's scheduler. Add this to your server's crontab:<br>
                    <code class="inline-block mt-1 bg-gray-100 px-2 py-1 rounded text-xs font-mono text-gray-700">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                </p>
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Task</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Command</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schedule</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                $cronJobs = [
                                    ['name' => 'Currency Exchange Rates', 'command' => 'currency:update', 'schedule' => 'Daily at 4:00 PM', 'enabled' => true],
                                    ['name' => 'Back-in-Stock Notifications', 'command' => 'stock:notify', 'schedule' => 'Every 15 minutes', 'enabled' => true],
                                    ['name' => 'Review Request Emails', 'command' => 'review:send', 'schedule' => 'Daily at 10:00 AM', 'enabled' => ($settings['reviews']['enabled'] ?? '0') === '1'],
                                    ['name' => 'SEO Suggestions (Claude AI)', 'command' => 'seo:generate', 'schedule' => 'Monthly on 1st at 3:00 AM', 'enabled' => !empty($settings['seo']['claude_api_key'] ?? '')],
                                    ['name' => 'Sitemap Generation', 'command' => 'sitemap:generate', 'schedule' => 'Daily at 4:00 AM', 'enabled' => true],
                                    ['name' => 'Low Stock Alerts', 'command' => 'stock:check-low', 'schedule' => 'Daily at 8:00 AM', 'enabled' => ($settings['stock']['low_stock_alert_enabled'] ?? '0') === '1'],
                                ];
                            @endphp
                            @foreach ($cronJobs as $job)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $job['name'] }}</td>
                                    <td class="px-4 py-3"><code class="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">{{ $job['command'] }}</code></td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $job['schedule'] }}</td>
                                    <td class="px-4 py-3">
                                        @if ($job['enabled'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Active</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Disabled</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-admin-layout>
