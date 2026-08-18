<x-admin3.settings-shell title="API & Sync" active="api">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">API & Sync</h1>
            <p class="v3-head__standing">The token the ERP pushes with, and what gets logged when it does.</p>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="erp-sync" />
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel title="ERP token">
            @if (session('new_erp_token'))
                <div class="v3-notice"
                     x-data="{ copied: false, async copy() {
                         if (!await window.v3Copy($refs.tokenInput.value)) return;
                         this.copied = true;
                         setTimeout(() => this.copied = false, 2000);
                     } }">
                    <p class="v3-notice__title">New token generated</p>
                    <p class="v3-notice__text">Copy it now. It will not be shown again in full.</p>
                    <div class="v3-noticerow">
                        <input type="text" x-ref="tokenInput" readonly value="{{ session('new_erp_token') }}"
                               onclick="this.select()" aria-label="New ERP token" class="v3-control v3-mono">
                        <button type="button" class="v3-btn v3-btn--secondary" @click="copy()" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                    </div>
                </div>
            @else
                <p class="v3-mono v3-secret__value">{{ $maskedKeys['erp_token'] ?? 'Not generated' }}</p>
            @endif
            <div class="v3-gap">
                <button type="button" class="v3-btn v3-btn--danger" @click="$dispatch('v3-confirm', 'regen-token')">
                    {{ isset($maskedKeys['erp_token']) ? 'Regenerate token' : 'Generate token' }}
                </button>
            </div>
        </x-admin3.panel>

        <x-admin3.confirm name="regen-token" title="Regenerate ERP token?"
            message="Regenerating breaks the ERP connection until the new token is entered there."
            confirm="Regenerate" :action="route('admin.settings.generate-erp-token')" method="POST" />

        <x-admin3.form :action="route('admin.settings.update')">
            <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'api') }}">
            <x-admin3.panel title="Sync logging">
                <label class="v3-toggle">
                    <input type="hidden" name="groups[api][api_sync_log_enabled]" value="0">
                    <input type="checkbox" name="groups[api][api_sync_log_enabled]" value="1"
                           @checked(($settings['api']['api_sync_log_enabled'] ?? '1') === '1')>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Log API sync requests to the database
                </label>
                <div class="v3-gap">
                    <a href="{{ route('admin.api-sync-logs.index') }}" class="v3-btn v3-btn--ghost v3-btn--sm">View API logs</a>
                </div>
            </x-admin3.panel>
        </x-admin3.form>
    </div>
</x-admin3.settings-shell>
