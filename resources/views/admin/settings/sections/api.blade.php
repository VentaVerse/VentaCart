<x-admin.settings-shell title="API">
    <div class="space-y-6">
        <x-admin.card title="ERP token" description="Used by the ERP system to authenticate API requests. Keep it secret.">
            <div class="space-y-4">
                @if (session('new_erp_token'))
                    <div class="rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] p-4"
                         x-data="{ copied: false, copy() {
                             navigator.clipboard.writeText($refs.newTokenInput.value).then(() => {
                                 this.copied = true;
                                 setTimeout(() => this.copied = false, 2000);
                             });
                             $refs.newTokenInput.select();
                         } }">
                        <p class="text-[13px] font-medium text-v-warning">New token generated — copy it now. It will not be shown again in full.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <input type="text" x-ref="newTokenInput" readonly value="{{ session('new_erp_token') }}"
                                   onclick="this.select()"
                                   class="v-control flex-1 h-[var(--control-h)] rounded-v-md border border-v-border bg-v-surface px-2.5 font-mono text-[13px] text-v-text">
                            <x-admin.button variant="secondary" type="button" @click="copy()" x-text="copied ? 'Copied!' : 'Copy'">Copy</x-admin.button>
                        </div>
                    </div>
                @else
                    <div>
                        <p class="text-[13px] font-mono text-v-text">
                            {{ $maskedKeys['erp_token'] ?? 'Not generated' }}
                        </p>
                    </div>
                @endif
                <x-admin.button variant="danger" type="button" @click="$dispatch('open-modal', 'regen-token')">
                    {{ isset($maskedKeys['erp_token']) ? 'Regenerate token' : 'Generate token' }}
                </x-admin.button>
            </div>
            <x-admin.confirm-dialog name="regen-token" title="Regenerate ERP token?"
                message="Regenerating breaks the ERP connection until it's updated there with the new token."
                confirm="Regenerate" action="{{ route('admin.settings.generate-erp-token') }}" method="POST" />
        </x-admin.card>

        <x-admin.card title="Sync logging" description="Log every ERP API sync request for troubleshooting.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="api">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'api') }}">
                <x-admin.field name="settings[api_sync_log_enabled]">
                    <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                        <x-admin.toggle name="settings[api_sync_log_enabled]" :checked="($settings['api']['api_sync_log_enabled'] ?? '1') === '1'" />
                        Log API sync requests to database
                    </label>
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save sync logging</x-admin.button>
                </x-admin.form-actions>
            </form>
            <p class="mt-4 text-[13px]">
                <a href="{{ route('admin.api-sync-logs.index') }}" class="text-v-accent hover:underline">View API logs &rarr;</a>
            </p>
        </x-admin.card>
    </div>
</x-admin.settings-shell>
