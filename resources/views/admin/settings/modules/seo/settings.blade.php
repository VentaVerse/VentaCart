{{--
    Settings-context page (settings/modules/seo/settings) — must show the
    Settings sub-sidebar, not the Marketing/SEO workspace nav (that was the
    "menus bring me to the Main Nav" bug: this page used to @include the SEO
    workspace nav wholesale, so every link out of here re-entered the SEO
    workspace). The only crossing back into the workspace is the single
    explicit link below; the only crossing in the other direction is the SEO
    workspace nav's single "Engine settings" item (partials/nav-v2.blade.php).
--}}
<x-admin.settings-shell title="SEO Engine" :wide="true">
        <div class="space-y-5">
                <a href="{{ route('admin.extensions.seo.dashboard') }}"
                   class="mb-1 flex w-fit items-center gap-1.5 text-[13px] text-v-secondary hover:text-v-text">
                    <x-admin.icon name="chevron-right" class="h-3 w-3 rotate-180" /> SEO workspace
                </a>

                <form method="POST" action="{{ route('admin.extensions.seo.save-settings') }}" class="space-y-5">
                    @csrf

                    {{-- ==================== API Connections ==================== --}}
                    <x-admin.card title="API Connections" description="Connect Claude AI and Google Search Console to power SEO optimization.">
                        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                            {{-- Claude --}}
                            <div class="space-y-4">
                                <h4 class="flex items-center gap-2 text-[13px] font-medium text-v-text">
                                    Claude AI
                                    @if ($maskedApiKey)
                                        <x-admin.badge tone="success">Connected</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="danger">Not configured</x-admin.badge>
                                    @endif
                                </h4>
                                <x-admin.field name="claude_api_key" label="API Key">
                                    @if ($maskedApiKey)
                                        <p class="mb-1 font-mono text-xs text-v-muted">{{ $maskedApiKey }}</p>
                                    @endif
                                    <x-admin.input type="password" name="claude_api_key"
                                        placeholder="{{ $maskedApiKey ? 'Leave blank to keep current' : 'sk-ant-...' }}" />
                                    <p class="text-xs text-v-muted">From <a href="https://console.anthropic.com/" target="_blank" class="text-v-accent hover:underline">console.anthropic.com</a></p>
                                </x-admin.field>
                                <div>
                                    <x-admin.button type="button" variant="secondary" size="sm" onclick="document.getElementById('test-claude-form').submit()">
                                        Test Connection
                                    </x-admin.button>
                                </div>
                                <x-admin.field name="claude_model" label="Model">
                                    <x-admin.select name="claude_model">
                                        <option value="claude-opus-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-opus-4-6' ? 'selected' : '' }}>Opus 4.6 — most capable</option>
                                        <option value="claude-sonnet-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-6' || empty($seoSettings['claude_model']) ? 'selected' : '' }}>Sonnet 4.6 — recommended</option>
                                        <option value="claude-sonnet-4-5-20250514" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-5-20250514' ? 'selected' : '' }}>Sonnet 4.5 — balanced</option>
                                        <option value="claude-haiku-4-5-20251001" {{ ($seoSettings['claude_model'] ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' }}>Haiku 4.5 — fast, cheap</option>
                                    </x-admin.select>
                                </x-admin.field>
                            </div>

                            {{-- GSC --}}
                            <div class="space-y-4">
                                <h4 class="flex items-center gap-2 text-[13px] font-medium text-v-text">
                                    Google Search Console
                                    @if ($gscCredentialsExist)
                                        <x-admin.badge tone="success">Connected</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="neutral">Optional</x-admin.badge>
                                    @endif
                                </h4>
                                <x-admin.field name="gsc_site_url" label="Site URL">
                                    <x-admin.input type="text" name="gsc_site_url" :value="$seoSettings['gsc_site_url'] ?? ''" placeholder="https://example.com" />
                                </x-admin.field>
                                <div>
                                    <label class="mb-1 block text-[13px] font-medium text-v-text">Service Account Credentials (JSON)</label>
                                    @if ($gscCredentialsExist)
                                        <div class="mb-1 flex items-center gap-2">
                                            <x-admin.icon name="check" class="h-4 w-4 text-v-accent" />
                                            <span class="text-xs text-v-secondary">gsc-credentials.json</span>
                                        </div>
                                    @endif
                                    <x-admin.button type="button" :variant="$gscCredentialsExist ? 'secondary' : 'primary'" size="sm"
                                        onclick="document.getElementById('upload-gsc-form').querySelector('input[type=file]').click()">
                                        {{ $gscCredentialsExist ? 'Replace' : 'Upload' }}
                                    </x-admin.button>
                                </div>
                                <div>
                                    <x-admin.button type="button" variant="secondary" size="sm" onclick="document.getElementById('test-gsc-form').submit()">
                                        Test Connection
                                    </x-admin.button>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- ==================== Optimization Rules ==================== --}}
                    <x-admin.card title="Optimization Rules" description="Control when the cron job should skip or re-optimize entities. These rules apply to automated (cron) runs only — manual &quot;Optimize&quot; buttons always work.">
                        <div class="max-w-2xl space-y-5">
                            {{-- Skip rules --}}
                            <div class="rounded-v-lg border border-v-border p-4">
                                <h4 class="mb-3 text-[13px] font-medium text-v-text">Skip Rules <span class="font-normal text-xs text-v-muted">— when to leave entities alone</span></h4>
                                <div class="space-y-3">
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="hidden" name="rule_skip_pending" value="0">
                                        <input type="checkbox" name="rule_skip_pending" value="1" {{ ($seoSettings['rule_skip_pending'] ?? '1') === '1' ? 'checked' : '' }}
                                            class="mt-0.5 rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                        <div>
                                            <span class="text-[13px] text-v-text">Skip if a pending suggestion exists</span>
                                            <p class="text-xs text-v-muted">Don't generate new suggestions for entities that haven't been reviewed yet.</p>
                                        </div>
                                    </label>

                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="hidden" name="rule_skip_recently_accepted" value="0">
                                        <input type="checkbox" name="rule_skip_recently_accepted" value="1" {{ ($seoSettings['rule_skip_recently_accepted'] ?? '1') === '1' ? 'checked' : '' }}
                                            class="mt-0.5 rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                        <div>
                                            <span class="text-[13px] text-v-text">Skip if recently optimized</span>
                                            <p class="text-xs text-v-muted">Don't re-optimize entities that were accepted within the refresh period below.</p>
                                        </div>
                                    </label>

                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="hidden" name="rule_skip_complete" value="0">
                                        <input type="checkbox" name="rule_skip_complete" value="1" {{ ($seoSettings['rule_skip_complete'] ?? '0') === '1' ? 'checked' : '' }}
                                            class="mt-0.5 rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                        <div>
                                            <span class="text-[13px] text-v-text">Skip if meta fields are already filled</span>
                                            <p class="text-xs text-v-muted">Don't touch entities that already have meta_title and meta_description, even if they were set manually.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            {{-- Process rules --}}
                            <div class="rounded-v-lg border border-v-border p-4">
                                <h4 class="mb-3 text-[13px] font-medium text-v-text">Process Rules <span class="font-normal text-xs text-v-muted">— when to always optimize</span></h4>
                                <div class="space-y-3">
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="hidden" name="rule_process_empty" value="0">
                                        <input type="checkbox" name="rule_process_empty" value="1" {{ ($seoSettings['rule_process_empty'] ?? '1') === '1' ? 'checked' : '' }}
                                            class="mt-0.5 rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                        <div>
                                            <span class="text-[13px] text-v-text">Always process entities with empty meta fields</span>
                                            <p class="text-xs text-v-muted">Entities with no meta_title or meta_description are always optimized regardless of other rules.</p>
                                        </div>
                                    </label>

                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="hidden" name="rule_process_rejected" value="0">
                                        <input type="checkbox" name="rule_process_rejected" value="1" {{ ($seoSettings['rule_process_rejected'] ?? '1') === '1' ? 'checked' : '' }}
                                            class="mt-0.5 rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                        <div>
                                            <span class="text-[13px] text-v-text">Re-process rejected suggestions</span>
                                            <p class="text-xs text-v-muted">If you rejected a suggestion, Claude will try again with a fresh take on the next run.</p>
                                        </div>
                                    </label>

                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 w-5"></div>
                                        <div class="flex-1">
                                            <label class="text-[13px] text-v-text">Refresh cycle (days)</label>
                                            <p class="mb-1 text-xs text-v-muted">Re-optimize accepted entities after this many days. Set to 0 to never re-optimize accepted items.</p>
                                            <x-admin.input type="number" name="rule_refresh_days" :value="$seoSettings['rule_refresh_days'] ?? '90'" min="0" max="365" class="w-24" />
                                            <span class="ml-1 text-xs text-v-muted">days (0 = never, 90 = quarterly)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Scope --}}
                            <div class="rounded-v-lg border border-v-border p-4">
                                <h4 class="mb-3 text-[13px] font-medium text-v-text">Scope <span class="font-normal text-xs text-v-muted">— which entity types to include in cron runs</span></h4>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog_posts' => 'Blog Posts', 'pages' => 'Pages'] as $key => $label)
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input type="hidden" name="rule_scope_{{ $key }}" value="0">
                                            <input type="checkbox" name="rule_scope_{{ $key }}" value="1" {{ ($seoSettings['rule_scope_' . $key] ?? '1') === '1' ? 'checked' : '' }}
                                                class="rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                            <span class="text-[13px] text-v-text">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Batch --}}
                            <div class="rounded-v-lg border border-v-border p-4">
                                <h4 class="mb-3 text-[13px] font-medium text-v-text">Batch Limit <span class="font-normal text-xs text-v-muted">— max entities per cron run</span></h4>
                                <div>
                                    <x-admin.input type="number" name="rule_batch_limit" :value="$seoSettings['rule_batch_limit'] ?? '0'" min="0" class="w-24" />
                                    <span class="ml-1 text-xs text-v-muted">per entity type (0 = unlimited)</span>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- ==================== General ==================== --}}
                    <x-admin.card title="General" description="Other SEO behavior settings.">
                        <div class="max-w-lg space-y-4">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="hidden" name="auto_accept" value="0">
                                <input type="checkbox" name="auto_accept" value="1" {{ ($seoSettings['auto_accept'] ?? '0') === '1' ? 'checked' : '' }}
                                    class="mt-0.5 rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                <div>
                                    <span class="text-[13px] text-v-text">Auto-accept suggestions</span>
                                    <p class="text-xs text-v-muted">When enabled, generated suggestions are applied immediately without manual review. Use with caution.</p>
                                </div>
                            </label>
                        </div>
                    </x-admin.card>

                    {{-- Save --}}
                    <div class="flex justify-end">
                        <x-admin.button type="submit" variant="primary">Save Settings</x-admin.button>
                    </div>
                </form>

                {{-- Hidden forms (outside main form) --}}
                <form id="test-claude-form" method="POST" action="{{ route('admin.extensions.seo.test-claude') }}" class="hidden">@csrf</form>
                <form id="test-gsc-form" method="POST" action="{{ route('admin.extensions.seo.test-gsc') }}" class="hidden">@csrf</form>
                <form id="upload-gsc-form" method="POST" action="{{ route('admin.extensions.seo.upload-gsc') }}" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <input type="file" name="gsc_credentials" accept=".json" onchange="this.form.submit()">
                </form>

                {{-- ==================== Scheduling ==================== --}}
                <x-admin.card title="Scheduling">
                    <div class="max-w-2xl">
                        <p class="mb-3 text-xs text-v-secondary">SEO suggestions run automatically via the Venta scheduler. Change the cadence or trigger a run manually from the Automations panel.</p>

                        <x-admin.button variant="primary" size="md" :href="route('admin.settings.section', 'automations')">
                            Manage in Settings → Automations
                        </x-admin.button>

                        <div class="mt-4 rounded-v-md border border-v-border bg-[color-mix(in_srgb,var(--warning)_10%,transparent)] p-3 text-xs text-v-warning">
                            <strong>Upgrading from an older Venta install?</strong> If your server's crontab still has a line running <code class="rounded bg-v-sunken px-1 text-v-text">seo:generate</code> directly, remove it — the command now fires through the universal scheduler and the old line will cause duplicate runs.
                        </div>
                    </div>
                </x-admin.card>
        </div>
</x-admin.settings-shell>
