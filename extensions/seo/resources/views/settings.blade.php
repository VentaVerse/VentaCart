{{-- The SEO engine's own configuration, living in the SEO space's
     dual menu (Setup group). Historically this wore the Settings
     rail to avoid handing out a foreign workspace menu; the space
     shell dissolves that problem, since the page now IS in its
     space. --}}
<x-admin.seo-shell title="Engine settings" active="settings">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Engine settings</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="seo" />
        </div>
    </div>

    <div class="v-settle">
        <x-admin.form :action="route('admin.extensions.seo.save-settings')" id="seo-engine-form">
            <x-admin.panel title="API connections">
                <div class="v-grid v-grid--2">
                    <div>
                        <div class="v-namerow v-gap-sm">
                            <strong>Claude AI</strong>
                            <span class="v-badge {{ $maskedApiKey ? 'v-badge--good' : 'v-badge--bad' }}">{{ $maskedApiKey ? 'Connected' : 'Not configured' }}</span>
                        </div>
                        <x-admin.field name="claude_api_key" label="API key"
                            :hint="$maskedApiKey ? 'Current key ends in ' . $maskedApiKey . '. Blank keeps it.' : null">
                            <input type="password" name="claude_api_key" autocomplete="off" class="v-control v-mono"
                                   placeholder="{{ $maskedApiKey ? 'Leave blank to keep current' : 'sk-ant-...' }}">
                        </x-admin.field>
                        <div class="v-gap">
                            <x-admin.field name="claude_model" label="Model">
                                <select name="claude_model" class="v-control">
                                    <option value="claude-opus-4-6" @selected(($seoSettings['claude_model'] ?? '') === 'claude-opus-4-6')>Opus 4.6: most capable</option>
                                    <option value="claude-sonnet-4-6" @selected(($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-6' || empty($seoSettings['claude_model']))>Sonnet 4.6: recommended</option>
                                    <option value="claude-sonnet-4-5-20250514" @selected(($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-5-20250514')>Sonnet 4.5: balanced</option>
                                    <option value="claude-haiku-4-5-20251001" @selected(($seoSettings['claude_model'] ?? '') === 'claude-haiku-4-5-20251001')>Haiku 4.5: fast, cheap</option>
                                </select>
                            </x-admin.field>
                        </div>
                        <div class="v-gap">
                            <button type="submit" form="test-claude-form" class="v-btn v-btn--secondary v-btn--sm">Test connection</button>
                        </div>
                    </div>

                    <div>
                        <div class="v-namerow v-gap-sm">
                            <strong>Google Search Console</strong>
                            <span class="v-badge {{ $gscCredentialsExist ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $gscCredentialsExist ? 'Connected' : 'Optional' }}</span>
                        </div>
                        <x-admin.field name="gsc_site_url" label="Site URL">
                            <input type="text" name="gsc_site_url" value="{{ old('gsc_site_url', $seoSettings['gsc_site_url'] ?? '') }}" class="v-control v-mono">
                        </x-admin.field>
                        <div class="v-gap">
                            <x-admin.field name="gsc_credentials" label="Service account credentials">
                                @if ($gscCredentialsExist)
                                    <span class="v-cell-quiet v-block v-mono">gsc-credentials.json</span>
                                @endif
                                <span class="v-actrow">
                                    <button type="button" class="v-btn {{ $gscCredentialsExist ? 'v-btn--secondary' : 'v-btn--primary' }} v-btn--sm"
                                            onclick="document.getElementById('upload-gsc-form').querySelector('input[type=file]').click()">
                                        {{ $gscCredentialsExist ? 'Replace JSON' : 'Upload JSON' }}
                                    </button>
                                    <button type="submit" form="test-gsc-form" class="v-btn v-btn--secondary v-btn--sm">Test connection</button>
                                </span>
                            </x-admin.field>
                        </div>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Optimization rules"
                note="Scheduled runs only. An Optimize button pressed by hand ignores every rule here.">
                <div class="v-narrow">
                    <div class="v-label v-gap-sm">Leave an entity alone when</div>
                    <label class="v-toggle">
                        <input type="hidden" name="rule_skip_pending" value="0">
                        <input type="checkbox" name="rule_skip_pending" value="1" @checked(($seoSettings['rule_skip_pending'] ?? '1') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        A suggestion is already waiting for review
                        <x-admin.help text="Nothing new is generated for an entity whose last suggestion has not been reviewed." />
                    </label>
                    <div class="v-gap-sm">
                        <label class="v-toggle">
                            <input type="hidden" name="rule_skip_recently_accepted" value="0">
                            <input type="checkbox" name="rule_skip_recently_accepted" value="1" @checked(($seoSettings['rule_skip_recently_accepted'] ?? '1') === '1')>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            It was optimized recently
                            <x-admin.help text="Measured against the refresh cycle below." />
                        </label>
                    </div>
                    <div class="v-gap-sm">
                        <label class="v-toggle">
                            <input type="hidden" name="rule_skip_complete" value="0">
                            <input type="checkbox" name="rule_skip_complete" value="1" @checked(($seoSettings['rule_skip_complete'] ?? '0') === '1')>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            Its meta fields are already filled
                            <x-admin.help text="Includes meta written by hand, not only meta Claude wrote." />
                        </label>
                    </div>

                    <div class="v-label v-gap">Always optimize when</div>
                    <label class="v-toggle">
                        <input type="hidden" name="rule_process_empty" value="0">
                        <input type="checkbox" name="rule_process_empty" value="1" @checked(($seoSettings['rule_process_empty'] ?? '1') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        The meta fields are empty
                        <x-admin.help text="This overrides every rule above." />
                    </label>
                    <div class="v-gap-sm">
                        <label class="v-toggle">
                            <input type="hidden" name="rule_process_rejected" value="0">
                            <input type="checkbox" name="rule_process_rejected" value="1" @checked(($seoSettings['rule_process_rejected'] ?? '1') === '1')>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            The last suggestion was rejected
                            <x-admin.help text="A rejected suggestion is regenerated from scratch on the next run." />
                        </label>
                    </div>
                    <div class="v-gap">
                        <x-admin.field name="rule_refresh_days" label="Refresh cycle (days)"
                            hint="0 never re-optimizes an accepted entity. 90 is quarterly.">
                            <input type="number" name="rule_refresh_days" value="{{ old('rule_refresh_days', $seoSettings['rule_refresh_days'] ?? '90') }}" min="0" max="365" class="v-control v-mono v-w-sort">
                        </x-admin.field>
                    </div>

                    <div class="v-label v-gap">Include in scheduled runs</div>
                    <div class="v-grid v-grid--3">
                        @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog_posts' => 'Blog Posts', 'pages' => 'Pages'] as $key => $label)
                            <label class="v-toggle">
                                <input type="hidden" name="rule_scope_{{ $key }}" value="0">
                                <input type="checkbox" name="rule_scope_{{ $key }}" value="1" @checked(($seoSettings['rule_scope_' . $key] ?? '1') === '1')>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div class="v-gap">
                        <x-admin.field name="rule_batch_limit" label="Batch limit"
                            hint="Entities per type per scheduled run. 0 is unlimited.">
                            <input type="number" name="rule_batch_limit" value="{{ old('rule_batch_limit', $seoSettings['rule_batch_limit'] ?? '0') }}" min="0" class="v-control v-mono v-w-sort">
                        </x-admin.field>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel title="General">
                <label class="v-toggle">
                    <input type="hidden" name="auto_accept" value="0">
                    <input type="checkbox" name="auto_accept" value="1" @checked(($seoSettings['auto_accept'] ?? '0') === '1')>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Auto-accept suggestions
                    <x-admin.help text="Suggestions are written to the live entity the moment they are generated, with no review step." />
                </label>
            </x-admin.panel>
        </x-admin.form>

        {{-- One-shot actions outside the doctrine form (forms do not nest). --}}
        <form id="test-claude-form" method="POST" action="{{ route('admin.extensions.seo.test-claude') }}" hidden>@csrf</form>
        <form id="test-gsc-form" method="POST" action="{{ route('admin.extensions.seo.test-gsc') }}" hidden>@csrf</form>
        <form id="upload-gsc-form" method="POST" action="{{ route('admin.extensions.seo.upload-gsc') }}" enctype="multipart/form-data" hidden>
            @csrf
            <input type="file" name="gsc_credentials" accept=".json" aria-label="Google Search Console credentials JSON" onchange="this.form.submit()">
        </form>

        <x-admin.panel title="Scheduling"
            note="Suggestions are generated by the Venta scheduler. If your crontab still runs seo:generate directly, remove that line or every entity is generated twice.">
            <a href="{{ route('admin.settings.section', 'automations') }}" class="v-btn v-btn--primary v-btn--sm">Open Automations</a>
        </x-admin.panel>
    </div>
</x-admin.seo-shell>
