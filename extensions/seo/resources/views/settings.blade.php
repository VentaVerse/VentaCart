{{-- The SEO engine's own configuration, living in the SEO space's
     dual menu (Setup group). Historically this wore the Settings
     rail to avoid handing out a foreign workspace menu; the space
     shell dissolves that problem, since the page now IS in its
     space. --}}
<x-admin3.seo-shell title="Engine settings" active="settings">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Engine settings</h1>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="seo" />
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.form :action="route('admin.extensions.seo.save-settings')" id="seo-engine-form">
            <x-admin3.panel title="API connections">
                <div class="v3-grid v3-grid--2">
                    <div>
                        <div class="v3-namerow v3-gap-sm">
                            <strong>Claude AI</strong>
                            <span class="v3-badge {{ $maskedApiKey ? 'v3-badge--good' : 'v3-badge--bad' }}">{{ $maskedApiKey ? 'Connected' : 'Not configured' }}</span>
                        </div>
                        <x-admin3.field name="claude_api_key" label="API key"
                            :hint="$maskedApiKey ? 'Current key ends in ' . $maskedApiKey . '. Blank keeps it.' : null">
                            <input type="password" name="claude_api_key" autocomplete="off" class="v3-control v3-mono"
                                   placeholder="{{ $maskedApiKey ? 'Leave blank to keep current' : 'sk-ant-...' }}">
                        </x-admin3.field>
                        <div class="v3-gap">
                            <x-admin3.field name="claude_model" label="Model">
                                <select name="claude_model" class="v3-control">
                                    <option value="claude-opus-4-6" @selected(($seoSettings['claude_model'] ?? '') === 'claude-opus-4-6')>Opus 4.6: most capable</option>
                                    <option value="claude-sonnet-4-6" @selected(($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-6' || empty($seoSettings['claude_model']))>Sonnet 4.6: recommended</option>
                                    <option value="claude-sonnet-4-5-20250514" @selected(($seoSettings['claude_model'] ?? '') === 'claude-sonnet-4-5-20250514')>Sonnet 4.5: balanced</option>
                                    <option value="claude-haiku-4-5-20251001" @selected(($seoSettings['claude_model'] ?? '') === 'claude-haiku-4-5-20251001')>Haiku 4.5: fast, cheap</option>
                                </select>
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap">
                            <button type="submit" form="test-claude-form" class="v3-btn v3-btn--secondary v3-btn--sm">Test connection</button>
                        </div>
                    </div>

                    <div>
                        <div class="v3-namerow v3-gap-sm">
                            <strong>Google Search Console</strong>
                            <span class="v3-badge {{ $gscCredentialsExist ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $gscCredentialsExist ? 'Connected' : 'Optional' }}</span>
                        </div>
                        <x-admin3.field name="gsc_site_url" label="Site URL">
                            <input type="text" name="gsc_site_url" value="{{ old('gsc_site_url', $seoSettings['gsc_site_url'] ?? '') }}" class="v3-control v3-mono">
                        </x-admin3.field>
                        <div class="v3-gap">
                            <x-admin3.field name="gsc_credentials" label="Service account credentials">
                                @if ($gscCredentialsExist)
                                    <span class="v3-cell-quiet v3-block v3-mono">gsc-credentials.json</span>
                                @endif
                                <span class="v3-actrow">
                                    <button type="button" class="v3-btn {{ $gscCredentialsExist ? 'v3-btn--secondary' : 'v3-btn--primary' }} v3-btn--sm"
                                            onclick="document.getElementById('upload-gsc-form').querySelector('input[type=file]').click()">
                                        {{ $gscCredentialsExist ? 'Replace JSON' : 'Upload JSON' }}
                                    </button>
                                    <button type="submit" form="test-gsc-form" class="v3-btn v3-btn--secondary v3-btn--sm">Test connection</button>
                                </span>
                            </x-admin3.field>
                        </div>
                    </div>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="Optimization rules"
                note="Scheduled runs only. An Optimize button pressed by hand ignores every rule here.">
                <div class="v3-narrow">
                    <div class="v3-label v3-gap-sm">Leave an entity alone when</div>
                    <label class="v3-toggle">
                        <input type="hidden" name="rule_skip_pending" value="0">
                        <input type="checkbox" name="rule_skip_pending" value="1" @checked(($seoSettings['rule_skip_pending'] ?? '1') === '1')>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        A suggestion is already waiting for review
                        <x-admin3.help text="Nothing new is generated for an entity whose last suggestion has not been reviewed." />
                    </label>
                    <div class="v3-gap-sm">
                        <label class="v3-toggle">
                            <input type="hidden" name="rule_skip_recently_accepted" value="0">
                            <input type="checkbox" name="rule_skip_recently_accepted" value="1" @checked(($seoSettings['rule_skip_recently_accepted'] ?? '1') === '1')>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            It was optimized recently
                            <x-admin3.help text="Measured against the refresh cycle below." />
                        </label>
                    </div>
                    <div class="v3-gap-sm">
                        <label class="v3-toggle">
                            <input type="hidden" name="rule_skip_complete" value="0">
                            <input type="checkbox" name="rule_skip_complete" value="1" @checked(($seoSettings['rule_skip_complete'] ?? '0') === '1')>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            Its meta fields are already filled
                            <x-admin3.help text="Includes meta written by hand, not only meta Claude wrote." />
                        </label>
                    </div>

                    <div class="v3-label v3-gap">Always optimize when</div>
                    <label class="v3-toggle">
                        <input type="hidden" name="rule_process_empty" value="0">
                        <input type="checkbox" name="rule_process_empty" value="1" @checked(($seoSettings['rule_process_empty'] ?? '1') === '1')>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        The meta fields are empty
                        <x-admin3.help text="This overrides every rule above." />
                    </label>
                    <div class="v3-gap-sm">
                        <label class="v3-toggle">
                            <input type="hidden" name="rule_process_rejected" value="0">
                            <input type="checkbox" name="rule_process_rejected" value="1" @checked(($seoSettings['rule_process_rejected'] ?? '1') === '1')>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            The last suggestion was rejected
                            <x-admin3.help text="A rejected suggestion is regenerated from scratch on the next run." />
                        </label>
                    </div>
                    <div class="v3-gap">
                        <x-admin3.field name="rule_refresh_days" label="Refresh cycle (days)"
                            hint="0 never re-optimizes an accepted entity. 90 is quarterly.">
                            <input type="number" name="rule_refresh_days" value="{{ old('rule_refresh_days', $seoSettings['rule_refresh_days'] ?? '90') }}" min="0" max="365" class="v3-control v3-mono v3-w-sort">
                        </x-admin3.field>
                    </div>

                    <div class="v3-label v3-gap">Include in scheduled runs</div>
                    <div class="v3-grid v3-grid--3">
                        @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog_posts' => 'Blog Posts', 'pages' => 'Pages'] as $key => $label)
                            <label class="v3-toggle">
                                <input type="hidden" name="rule_scope_{{ $key }}" value="0">
                                <input type="checkbox" name="rule_scope_{{ $key }}" value="1" @checked(($seoSettings['rule_scope_' . $key] ?? '1') === '1')>
                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div class="v3-gap">
                        <x-admin3.field name="rule_batch_limit" label="Batch limit"
                            hint="Entities per type per scheduled run. 0 is unlimited.">
                            <input type="number" name="rule_batch_limit" value="{{ old('rule_batch_limit', $seoSettings['rule_batch_limit'] ?? '0') }}" min="0" class="v3-control v3-mono v3-w-sort">
                        </x-admin3.field>
                    </div>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="General">
                <label class="v3-toggle">
                    <input type="hidden" name="auto_accept" value="0">
                    <input type="checkbox" name="auto_accept" value="1" @checked(($seoSettings['auto_accept'] ?? '0') === '1')>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Auto-accept suggestions
                    <x-admin3.help text="Suggestions are written to the live entity the moment they are generated, with no review step." />
                </label>
            </x-admin3.panel>
        </x-admin3.form>

        {{-- One-shot actions outside the doctrine form (forms do not nest). --}}
        <form id="test-claude-form" method="POST" action="{{ route('admin.extensions.seo.test-claude') }}" hidden>@csrf</form>
        <form id="test-gsc-form" method="POST" action="{{ route('admin.extensions.seo.test-gsc') }}" hidden>@csrf</form>
        <form id="upload-gsc-form" method="POST" action="{{ route('admin.extensions.seo.upload-gsc') }}" enctype="multipart/form-data" hidden>
            @csrf
            <input type="file" name="gsc_credentials" accept=".json" aria-label="Google Search Console credentials JSON" onchange="this.form.submit()">
        </form>

        <x-admin3.panel title="Scheduling"
            note="Suggestions are generated by the Venta scheduler. If your crontab still runs seo:generate directly, remove that line or every entity is generated twice.">
            <a href="{{ route('admin.settings.section', 'automations') }}" class="v3-btn v3-btn--primary v3-btn--sm">Open Automations</a>
        </x-admin3.panel>
    </div>
</x-admin3.seo-shell>
