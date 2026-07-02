<x-admin-layout>
    <x-slot name="header">Courier Settings</x-slot>

    <div class="max-w-2xl space-y-4" x-data="{ mode: '{{ $settings['quadx_mode'] ?? 'staging' }}' }">

        @if (session('success'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.fulfillment.index') }}" class="text-sm text-indigo-600 hover:underline">← Back to Fulfillment</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">QuadX</h3>
                <p class="text-xs text-gray-500 mt-0.5">Philippine courier (LBC-backed). Book confirmed orders from the Fulfillment page.</p>
            </div>

            <form method="POST" action="{{ route('admin.fulfillment.settings.update') }}" class="p-5 space-y-5">
                @csrf

                {{-- Enable Fulfillment (surfaces the Sales › Order Fulfillment page) --}}
                <div class="flex items-center gap-3 pb-4 border-b border-gray-100">
                    <input type="hidden" name="settings[fulfillment_enabled]" value="0">
                    <input type="checkbox" name="settings[fulfillment_enabled]" value="1"
                        {{ ($settings['fulfillment_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <span class="text-sm text-gray-700">Enable Fulfillment (shows the Sales &rsaquo; Order Fulfillment page)</span>
                </div>

                {{-- Enabled --}}
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="hidden" name="settings[quadx_enabled]" value="0">
                    <input type="checkbox" name="settings[quadx_enabled]" value="1" {{ ($settings['quadx_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                    Enable QuadX
                </label>

                {{-- Mode --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
                    <select name="settings[quadx_mode]" x-model="mode" class="w-48 rounded-md border-gray-300 text-sm">
                        <option value="staging">Staging</option>
                        <option value="production">Production</option>
                    </select>
                </div>

                {{-- Staging creds --}}
                <template x-if="mode === 'staging'">
                    <div class="space-y-3 border-l-4 border-amber-400 pl-4">
                        <p class="text-xs font-medium text-amber-600 uppercase tracking-wide">Staging Credentials</p>
                        <div>
                            <label class="block text-sm text-gray-700">API Key
                                @if (!empty($masked['quadx_staging_api_key'])) <span class="text-xs text-gray-400 font-mono">(current: {{ $masked['quadx_staging_api_key'] }})</span> @endif
                            </label>
                            <input type="password" name="settings[quadx_staging_api_key]" autocomplete="off" placeholder="Enter to change" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700">Secret Key
                                @if (!empty($masked['quadx_staging_secret_key'])) <span class="text-xs text-gray-400 font-mono">(current: {{ $masked['quadx_staging_secret_key'] }})</span> @endif
                            </label>
                            <input type="password" name="settings[quadx_staging_secret_key]" autocomplete="off" placeholder="Enter to change" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                </template>

                {{-- Production creds --}}
                <template x-if="mode === 'production'">
                    <div class="space-y-3 border-l-4 border-green-500 pl-4">
                        <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Production Credentials</p>
                        <div>
                            <label class="block text-sm text-gray-700">API Key
                                @if (!empty($masked['quadx_production_api_key'])) <span class="text-xs text-gray-400 font-mono">(current: {{ $masked['quadx_production_api_key'] }})</span> @endif
                            </label>
                            <input type="password" name="settings[quadx_production_api_key]" autocomplete="off" placeholder="Enter to change" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700">Secret Key
                                @if (!empty($masked['quadx_production_secret_key'])) <span class="text-xs text-gray-400 font-mono">(current: {{ $masked['quadx_production_secret_key'] }})</span> @endif
                            </label>
                            <input type="password" name="settings[quadx_production_secret_key]" autocomplete="off" placeholder="Enter to change" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                </template>

                {{-- Test Connection — hits the live QuadX API with the saved credentials --}}
                <div x-data="{ testing:false, result:null }" class="mt-2">
                    <button type="button" @click="testing=true; result=null;
                        fetch('{{ route('admin.fulfillment.test-connection') }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
                        .then(r=>r.json()).then(d=>result=d).finally(()=>testing=false)"
                        class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50">
                        <span x-show="!testing">Test Connection</span><span x-show="testing">Testing…</span>
                    </button>
                    <span x-show="result" x-text="result?.message" :class="result?.ok ? 'text-emerald-600' : 'text-red-600'" class="ml-2 text-sm"></span>
                </div>

                {{-- Order status mapping — QuadX status → Venta order status --}}
                <div class="mt-8">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Order status mapping</h3>
                    <p class="text-xs text-gray-500 mb-3">When QuadX reports a status, set the Venta order status. Leave blank to only track it on the fulfillment page.</p>
                    <table class="w-full text-sm">
                        <thead><tr class="text-[11px] uppercase text-gray-400">
                            <th class="text-left py-1">QuadX status</th><th class="text-left py-1">Venta order status</th><th class="text-left py-1">Notify customer</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($quadxStatuses as $key => $label)
                            <tr class="border-t border-gray-100">
                                <td class="py-2">{{ $label }}</td>
                                <td class="py-2">
                                    <select name="settings[quadx_status_map][{{ $key }}][order_status_id]" class="rounded-md border-gray-300 text-sm">
                                        <option value="">— none —</option>
                                        @foreach ($orderStatuses as $id => $name)
                                            <option value="{{ $id }}" {{ (string) ($statusMap[$key]['order_status_id'] ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2">
                                    <input type="hidden" name="settings[quadx_status_map][{{ $key }}][notify]" value="0">
                                    <input type="checkbox" name="settings[quadx_status_map][{{ $key }}][notify]" value="1"
                                        {{ !empty($statusMap[$key]['notify']) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">Save</button>
                </div>
            </form>
        </div>

        {{-- Webhook URL — this store's own URL, copy and send to QuadX --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200" x-data="{ copied: false }">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Webhook URL</h3>
                <p class="text-xs text-gray-500 mt-0.5">QuadX has no UI to set this — email/Slack them this URL so they push status updates here. Unique to this store.</p>
            </div>
            <div class="p-5">
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $webhookUrl }}" x-ref="hook" class="flex-1 rounded-md border-gray-300 bg-gray-50 text-xs font-mono text-gray-700">
                    <button type="button" @click="navigator.clipboard.writeText($refs.hook.value); copied=true; setTimeout(()=>copied=false,1500)"
                        class="px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-lg hover:bg-gray-900 whitespace-nowrap">
                        <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied ✓</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
