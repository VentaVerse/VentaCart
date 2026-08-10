{{--
    Settings-context page (settings/modules/seo/settings): it shows the
    Settings rail, not the Marketing/SEO workspace rail (that was the
    "menus bring me to the Main Nav" bug: this page used to @include the SEO
    workspace nav wholesale, so every link out of here re-entered the SEO
    workspace). The only crossing back into the workspace is the single
    explicit link below; the only crossing in the other direction is the SEO
    workspace's single "Engine settings" item.
--}}
<x-admin.settings-shell title="SEO Engine">
    <x-slot:actions>
        <x-admin.button variant="ghost" :href="route('admin.extensions.seo.dashboard')">SEO workspace</x-admin.button>
    </x-slot:actions>

    <div class="space-y-5">
        <form method="POST" action="{{ route('admin.extensions.seo.save-settings') }}" class="space-y-5">
            @csrf

            <x-admin.card title="API Connections">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <div class="space-y-4">
                        <h3 class="flex items-center gap-2 v-subhead">
                            Claude AI
                            <x-admin.badge :tone="$maskedApiKey ? 'success' : 'danger'">{{ $maskedApiKey ? 'Connected' : 'Not configured' }}</x-admin.badge>
                        </h3>
                        <x-admin.field name="claude_api_key" label="API key"
                            :help="$maskedApiKey ? 'Current key ends in ' . $maskedApiKey . '. Blank keeps it.' : null">
                            <x-admin.input type="password" name="claude_api_key"
                                placeholder="{{ $maskedApiKey ? 'Leave blank to keep current' : 'sk-ant-...' }}" />
                        </x-admin.field>
                        <x-admin.field name="claude_model" label="Model">
                            <x-admin.select name="claude_model">
                                <option value="claude-opus-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-opus-4-6' ? 'selected' : '' }}>Opus 4.6: most capable</option>
                                <option value="claude-sonnet-4-6" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-6' || empty($seoSettings['claude_model']) ? 'selected' : '' }}>Sonnet 4.6: recommended</option>
                                <option value="claude-sonnet-4-5-20250514" {{ ($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-5-20250514' ? 'selected' : '' }}>Sonnet 4.5: balanced</option>
                                <option value="claude-haiku-4-5-20251001" {{ ($seoSettings['claude_model'] ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' }}>Haiku 4.5: fast, cheap</option>
                            </x-admin.select>
                        </x-admin.field>
                        <x-admin.button type="button" variant="secondary" size="sm" onclick="document.getElementById('test-claude-form').submit()">
                            Test connection
                        </x-admin.button>
                    </div>

                    <div class="space-y-4">
                        <h3 class="flex items-center gap-2 v-subhead">
                            Google Search Console
                            <x-admin.badge :tone="$gscCredentialsExist ? 'success' : 'neutral'">{{ $gscCredentialsExist ? 'Connected' : 'Optional' }}</x-admin.badge>
                        </h3>
                        <x-admin.field name="gsc_site_url" label="Site URL">
                            <x-admin.input type="text" name="gsc_site_url" :value="$seoSettings['gsc_site_url'] ?? ''" placeholder="https://example.com" />
                        </x-admin.field>
                        <x-admin.field name="gsc_credentials" label="Service account credentials">
                            @if ($gscCredentialsExist)
                                <p class="mb-1 flex items-center gap-2 text-xs text-v-secondary">
                                    <x-admin.icon name="check" class="h-4 w-4 text-v-accent" /> gsc-credentials.json
                                </p>
                            @endif
                            <div class="flex items-center gap-2">
                                <x-admin.button type="button" :variant="$gscCredentialsExist ? 'secondary' : 'primary'" size="sm"
                                    onclick="document.getElementById('upload-gsc-form').querySelector('input[type=file]').click()">
                                    {{ $gscCredentialsExist ? 'Replace JSON' : 'Upload JSON' }}
                                </x-admin.button>
                                <x-admin.button type="button" variant="secondary" size="sm" onclick="document.getElementById('test-gsc-form').submit()">
                                    Test connection
                                </x-admin.button>
                            </div>
                        </x-admin.field>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Optimization Rules">
                <x-slot name="headerActions">
                    <x-admin.hint label="What these rules govern" class="v-hint--end">Scheduled runs only. An Optimize button pressed by hand ignores every rule here.</x-admin.hint>
                </x-slot>
                <div class="max-w-2xl space-y-6">
                    <fieldset>
                        <legend class="v-label mb-3">Leave an entity alone when</legend>
                        <div class="space-y-3">
                            <x-admin.field name="rule_skip_pending" help="Nothing new is generated for an entity whose last suggestion has not been reviewed.">
                                <input type="hidden" name="rule_skip_pending" value="0">
                                <x-admin.checkbox name="rule_skip_pending" label="A suggestion is already waiting for review"
                                    :checked="($seoSettings['rule_skip_pending'] ?? '1') === '1'" />
                            </x-admin.field>
                            <x-admin.field name="rule_skip_recently_accepted" help="Measured against the refresh cycle below.">
                                <input type="hidden" name="rule_skip_recently_accepted" value="0">
                                <x-admin.checkbox name="rule_skip_recently_accepted" label="It was optimized recently"
                                    :checked="($seoSettings['rule_skip_recently_accepted'] ?? '1') === '1'" />
                            </x-admin.field>
                            <x-admin.field name="rule_skip_complete" help="Includes meta written by hand, not only meta Claude wrote.">
                                <input type="hidden" name="rule_skip_complete" value="0">
                                <x-admin.checkbox name="rule_skip_complete" label="Its meta fields are already filled"
                                    :checked="($seoSettings['rule_skip_complete'] ?? '0') === '1'" />
                            </x-admin.field>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="v-label mb-3">Always optimize when</legend>
                        <div class="space-y-3">
                            <x-admin.field name="rule_process_empty" help="This overrides every rule above.">
                                <input type="hidden" name="rule_process_empty" value="0">
                                <x-admin.checkbox name="rule_process_empty" label="The meta fields are empty"
                                    :checked="($seoSettings['rule_process_empty'] ?? '1') === '1'" />
                            </x-admin.field>
                            <x-admin.field name="rule_process_rejected" help="A rejected suggestion is regenerated from scratch on the next run.">
                                <input type="hidden" name="rule_process_rejected" value="0">
                                <x-admin.checkbox name="rule_process_rejected" label="The last suggestion was rejected"
                                    :checked="($seoSettings['rule_process_rejected'] ?? '1') === '1'" />
                            </x-admin.field>
                            <x-admin.field name="rule_refresh_days" label="Refresh cycle (days)" help="0 never re-optimizes an accepted entity. 90 is quarterly.">
                                <x-admin.input type="number" name="rule_refresh_days" :value="$seoSettings['rule_refresh_days'] ?? '90'" min="0" max="365" class="w-24" />
                            </x-admin.field>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="v-label mb-3">Include in scheduled runs</legend>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog_posts' => 'Blog Posts', 'pages' => 'Pages'] as $key => $label)
                                <div>
                                    <input type="hidden" name="rule_scope_{{ $key }}" value="0">
                                    <x-admin.checkbox name="rule_scope_{{ $key }}" :label="$label"
                                        :checked="($seoSettings['rule_scope_' . $key] ?? '1') === '1'" />
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <x-admin.field name="rule_batch_limit" label="Batch limit" help="Entities per type per scheduled run. 0 is unlimited.">
                        <x-admin.input type="number" name="rule_batch_limit" :value="$seoSettings['rule_batch_limit'] ?? '0'" min="0" class="w-24" />
                    </x-admin.field>
                </div>
            </x-admin.card>

            <x-admin.card title="General">
                <x-admin.field name="auto_accept" help="Suggestions are written to the live entity the moment they are generated, with no review step.">
                    <input type="hidden" name="auto_accept" value="0">
                    <x-admin.checkbox name="auto_accept" label="Auto-accept suggestions"
                        :checked="($seoSettings['auto_accept'] ?? '0') === '1'" />
                </x-admin.field>
            </x-admin.card>

            <div class="flex justify-end">
                <x-admin.button type="submit" variant="primary">Save Settings</x-admin.button>
            </div>
        </form>

        {{-- Outside the main form, because forms do not nest. --}}
        <form id="test-claude-form" method="POST" action="{{ route('admin.extensions.seo.test-claude') }}" class="hidden">@csrf</form>
        <form id="test-gsc-form" method="POST" action="{{ route('admin.extensions.seo.test-gsc') }}" class="hidden">@csrf</form>
        <form id="upload-gsc-form" method="POST" action="{{ route('admin.extensions.seo.upload-gsc') }}" enctype="multipart/form-data" class="hidden">
            @csrf
            <input type="file" name="gsc_credentials" accept=".json" aria-label="Google Search Console credentials JSON" onchange="this.form.submit()">
        </form>

        <x-admin.card title="Scheduling">
            <div class="flex items-center gap-2">
                <x-admin.button variant="primary" size="sm" :href="route('admin.settings.section', 'automations')">
                    Open Automations
                </x-admin.button>
                <x-admin.hint label="About the seo:generate schedule">Suggestions are generated by the Venta scheduler. If your crontab still runs seo:generate directly, remove that line or every entity is generated twice.</x-admin.hint>
            </div>
        </x-admin.card>
    </div>
</x-admin.settings-shell>
