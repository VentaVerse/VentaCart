<x-admin.shell title="ERP Sync">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>ERP Sync</span>
            </div>
            <h1 class="v-head__title">ERP Sync</h1>
            <p class="v-head__standing">The keys anything may call the API with, what each one may reach, and what gets logged when it does.</p>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="erp-sync" />
        </div>
    </div>

    <div class="v-settle">
        @if (session('new_api_key'))
            <div class="v-notice"
                 x-data="{ copied: false, async copy() {
                     if (!await window.vCopy($refs.keyInput.value)) return;
                     this.copied = true;
                     setTimeout(() => this.copied = false, 2000);
                 } }">
                <p class="v-notice__title">Key created: {{ session('new_api_key_label') }}</p>
                <p class="v-notice__text">Copy it now. Only a fingerprint is stored, so it cannot be shown again.</p>
                <div class="v-noticerow">
                    <input type="text" x-ref="keyInput" readonly value="{{ session('new_api_key') }}"
                           onclick="this.select()" aria-label="New API key" class="v-control v-mono">
                    <button type="button" class="v-btn v-btn--secondary" @click="copy()" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                </div>
            </div>
        @endif

        <x-admin.panel title="API keys">
            @if ($keys->isEmpty())
                <p class="v-cell-quiet">No keys yet. Nothing can call the API until one is created.</p>
            @else
                <div class="v-scroll">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Key</th>
                                <th>Permissions</th>
                                <th>Last used</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($keys as $key)
                                <tr>
                                    <td>{{ $key->label }}</td>
                                    <td class="v-mono v-cell-quiet">{{ $key->prefix }}&hellip;</td>
                                    <td>
                                        @php
                                            $granted = [];
                                            foreach ($areas as $area) {
                                                $modes = $key->abilities[$area] ?? [];
                                                if ($modes) {
                                                    $granted[] = ucfirst($area) . ' ' . implode(' + ', $modes);
                                                }
                                            }
                                        @endphp
                                        {{ implode(', ', $granted) }}
                                    </td>
                                    {{-- A key nobody has used is one it is safe to delete. --}}
                                    <td class="v-cell-quiet">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="v-cell-num">
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                                @click="$dispatch('v-confirm', 'delete-key-{{ $key->id }}')">Delete</button>
                                        <x-admin.confirm :name="'delete-key-' . $key->id" title="Delete this key?"
                                            :message="'Anything using ' . $key->label . ' stops working immediately. This cannot be undone.'"
                                            confirm="Delete" :action="route('admin.erp-sync.keys.destroy', $key)" method="DELETE" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        <x-admin.form :action="route('admin.erp-sync.keys.store')" method="POST" :header-save="false" save-label="Create key">
            <x-admin.panel title="New key">
                <x-admin.field name="label" label="Name" required>
                    <input type="text" name="label" maxlength="60" required class="v-control"
                           placeholder="ERP, Reporting, Warehouse app">
                </x-admin.field>

                <div class="v-gap">
                    <div class="v-label">Permissions</div>
                    <div class="v-grid v-grid--2">
                        @foreach ($areas as $area)
                            <div class="v-well">
                                <div class="v-label">{{ ucfirst($area) }}</div>
                                @foreach ($modes as $mode)
                                    <label class="v-check">
                                        <input type="checkbox" name="abilities[{{ $area }}][]" value="{{ $mode }}">
                                        <span>{{ ucfirst($mode) }}</span>
                                    </label>
                                @endforeach
                                @if ($area === 'reviews')
                                    {{-- Honest rather than tidy: the tick exists so a
                                         key is ready when a read endpoint is added,
                                         and today there is none to govern. --}}
                                    <p class="v-cell-quiet">Read has no endpoint yet.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-admin.panel>
        </x-admin.form>

        <x-admin.form :action="route('admin.settings.update')" :header-save="false">
            <input type="hidden" name="_redirect" value="{{ route('admin.erp-sync.settings') }}">
            <x-admin.panel title="Sync logging">
                <label class="v-toggle">
                    <input type="hidden" name="groups[api][api_sync_log_enabled]" value="0">
                    <input type="checkbox" name="groups[api][api_sync_log_enabled]" value="1"
                           @checked(($settings['api']['api_sync_log_enabled'] ?? '1') === '1')>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Log API sync requests to the database
                </label>
                <div class="v-gap">
                    <a href="{{ route('admin.api-sync-logs.index') }}" class="v-btn v-btn--ghost v-btn--sm">View API logs</a>
                </div>
            </x-admin.panel>
        </x-admin.form>
    </div>
</x-admin.shell>
