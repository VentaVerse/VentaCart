@php
    $initialAudienceType = old('audience_type', $campaign->audience_type ?? 'all');
    $initialGroupId = old('group_id', $campaign->audience_params['group_id'] ?? null);
    $initialCustomerIds = old('customer_ids', $campaign->audience_params['customer_ids'] ?? []);
    $locked = $campaign && $campaign->status === 'sent';
@endphp
<x-admin.shell :title="$campaign ? 'Edit campaign' : 'New campaign'">
    @push('head')
        @vite('extensions/email-campaigns/resources/css/email-campaigns.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.email-campaigns.index') }}">Email</a>
                <span aria-hidden="true">/</span>
                <span>{{ $campaign ? $campaign->subject : 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $campaign ? 'Edit campaign' : 'New campaign' }}</h1>
        </div>
    </div>

    <div class="v-settle"
         x-data="campaignComposer(@js(['audienceType' => $initialAudienceType, 'groupId' => $initialGroupId, 'countsUrl' => route('admin.email-campaigns.recipient-count')]))"
         x-init="fetchCounts()">

        @if ($locked)
            <div class="v-notice v-campaign-sent-notice">
                <p class="v-notice__title">Already sent</p>
                <p class="v-notice__text">This campaign has gone out and is kept as a record. It can no longer be changed or sent again.</p>
            </div>
        @endif

        <form method="POST" id="v-campaign-form"
              action="{{ $campaign ? route('admin.email-campaigns.update', $campaign) : route('admin.email-campaigns.store') }}">
            @csrf
            @if ($campaign) @method('PUT') @endif
            <input type="hidden" name="action" id="v-campaign-action" value="draft">

            <fieldset @disabled($locked) class="v-settle">
                @if (!$campaign && $templates->isNotEmpty())
                    <x-admin.panel title="Start from template">
                        <x-admin.field name="from_template" label="Template">
                            <select id="v-campaign-from-template" class="v-control"
                                    onchange="if (this.value) { window.location = this.value; }">
                                <option value="">Blank campaign</option>
                                @foreach ($templates as $t)
                                    <option value="{{ route('admin.email-campaigns.create', ['from_template' => $t->id]) }}"
                                            @selected($prefill && $prefill->id === $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                    </x-admin.panel>
                @endif

                <x-admin.panel title="Message">
                    <x-admin.field name="subject" label="Subject" required>
                        <input type="text" id="v-campaign-subject" name="subject" required maxlength="255"
                               class="v-control" value="{{ old('subject', $campaign->subject ?? $prefill->subject ?? '') }}">
                    </x-admin.field>

                    <div class="v-gap">
                        <x-admin.field name="body" label="Body">
                            <div class="v-tokenbar" role="toolbar" aria-label="Insert placeholder">
                                @foreach ($tokens as $key => $label)
                                    <button type="button" class="v-token" data-token="{{ $key }}"
                                            onclick="insertCampaignToken(this.dataset.token)">
                                        <span class="v-token__label">{{ $label }}</span>
                                        <span class="v-token__key">{{ '{' . $key . '}' }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <textarea id="v-campaign-body" name="body" rows="16" class="v-control">{{ old('body', $campaign->body ?? $prefill->body ?? '') }}</textarea>
                        </x-admin.field>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Audience">
                    <div class="v-grid v-grid--2">
                        <x-admin.field name="audience_type" label="Send to" required>
                            <select name="audience_type" id="v-campaign-audience" class="v-control"
                                    x-model="audienceType" @change="fetchCounts()">
                                @foreach ($audiences as $type => $label)
                                    <option value="{{ $type }}" @selected($type === $initialAudienceType)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>

                        <div x-show="audienceType === 'group'" x-cloak>
                            <x-admin.field name="group_id" label="Customer group">
                                <select name="group_id" class="v-control" x-model.number="groupId" @change="fetchCounts()">
                                    <option value="">Choose a group</option>
                                    @foreach ($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </x-admin.field>
                        </div>
                    </div>

                    <div class="v-gap" x-show="audienceType === 'selected'" x-cloak @change="fetchCounts()">
                        <x-admin.picklist name="customer_ids" label="Customers"
                            :items="$customers->map(fn ($c) => ['id' => $c->id, 'name' => trim($c->first_name . ' ' . $c->last_name) . ' (' . $c->email . ')'])->all()"
                            :selected="$initialCustomerIds" />
                    </div>

                    <div class="v-stats v-gap">
                        <div class="v-stat">
                            <div class="v-stat__label">Audience</div>
                            <div class="v-stat__value" x-text="loading ? '…' : audienceTotal">0</div>
                        </div>
                        <div class="v-stat">
                            <div class="v-stat__label">Will receive</div>
                            <div class="v-stat__value" x-text="loading ? '…' : willReceive">0</div>
                        </div>
                    </div>
                    <p class="v-audiencegap" x-show="!loading && willReceive < audienceTotal" x-cloak>
                        <span x-text="audienceTotal - willReceive"></span> opted out of email and will be skipped.
                    </p>
                </x-admin.panel>
            </fieldset>

            @unless ($locked)
                <div class="v-gap v-actrow">
                    <button type="button" class="v-btn v-btn--secondary"
                            @click="$dispatch('v-confirm', 'save-as-template')">Save as template</button>
                    <button type="submit" class="v-btn v-btn--secondary"
                            onclick="document.getElementById('v-campaign-action').value = 'draft'">Save draft</button>
                    <button type="button" class="v-btn v-btn--primary"
                            @click="$dispatch('v-confirm', 'send-campaign')">Send now</button>
                    <a href="{{ route('admin.email-campaigns.index') }}" class="v-btn v-btn--ghost">Cancel</a>
                </div>
            @endunless
        </form>

        @unless ($locked)
            {{-- The dialog's own form never actually posts: submitting it is
                 intercepted below and redirected into #v-campaign-form with
                 action=send, so the send carries the subject, body, and
                 audience the admin was actually looking at, not a second
                 copy of them. The :action given here is the real store/update
                 route as a safety fallback in case that interception is ever
                 unavailable (e.g. a script error) -- submitting it directly
                 fails validation (no subject/body) rather than sending
                 anything. --}}
            <x-admin.confirm name="send-campaign" title="Send this campaign now?"
                message="Every recipient who has not opted out of email gets this immediately. This cannot be undone."
                confirm="Send now" :danger="false"
                :action="$campaign ? route('admin.email-campaigns.update', $campaign) : route('admin.email-campaigns.store')"
                :method="$campaign ? 'PUT' : 'POST'" />

            {{-- Like the send dialog above, this form never actually posts:
                 saving as a template must not navigate away from an unsaved
                 compose screen, so submitting it is intercepted below and
                 sent with vPost() instead. The :action is a fallback for a
                 script error, same as the send dialog. --}}
            <x-admin.confirm name="save-as-template" title="Save as template"
                message="Saves the subject and body as a reusable starting point. The audience and any saved recipients are not part of a template."
                confirm="Save template" :danger="false"
                :action="route('admin.email-campaigns.templates.store')" method="POST">
                <x-admin.field name="name" label="Template name" required>
                    <input type="text" name="name" id="v-save-template-name" class="v-control" required maxlength="128">
                </x-admin.field>
            </x-admin.confirm>
        @endunless

        <x-admin.panel title="Test send">
            <div class="v-actrow">
                <button type="button" class="v-btn v-btn--secondary" onclick="sendCampaignTest()">Send test to me</button>
            </div>
        </x-admin.panel>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v-campaign-body',
            height: 420,
            menubar: 'file edit view insert format table',
            plugins: 'lists link autolink code table image fullscreen',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | alignleft aligncenter alignright | link image table | removeformat code fullscreen',
            content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; } img { max-width: 100%; height: auto; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            relative_urls: false,
            remove_script_host: true,
            image_advtab: true,
            image_dimensions: true,
            readonly: {{ $locked ? 'true' : 'false' }},
            setup: function (editor) {
                // The editor lives outside the form's input events; sync it
                // back to the textarea on every edit, so the test-send copy
                // and a plain submit both see the latest content.
                editor.on('input change undo redo', function () {
                    editor.save();
                    var form = document.getElementById('v-campaign-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });

        function insertCampaignToken(key) {
            var editor = tinymce.get('v-campaign-body');
            if (editor && !editor.readonly) {
                editor.insertContent('{' + key + '}');
                editor.focus();
                editor.save();
            }
        }

        // The "Send now" confirm dialog's own <form> is never meant to post
        // anywhere: accepting it should submit the real campaign form with
        // action=send, carrying whatever the admin has actually typed.
        // Captured at the document so it fires before the dialog's own
        // submission reaches the network, regardless of which x-admin.confirm
        // instance on the page raised it.
        document.addEventListener('submit', function (e) {
            var modal = e.target.closest('.v-modal');
            if (!modal) return;

            if (modal.getAttribute('aria-labelledby') === 'vc-send-campaign') {
                e.preventDefault();
                var campaignForm = document.getElementById('v-campaign-form');
                if (!campaignForm) return;
                if (tinymce.get('v-campaign-body')) tinymce.triggerSave();
                document.getElementById('v-campaign-action').value = 'send';
                // requestSubmit(), not submit(): it still runs the browser's own
                // required-field validation (a blank subject shows the native
                // bubble instead of silently posting), which plain submit() skips.
                if (campaignForm.requestSubmit) {
                    campaignForm.requestSubmit();
                } else {
                    campaignForm.submit();
                }
                return;
            }

            if (modal.getAttribute('aria-labelledby') === 'vc-save-as-template') {
                e.preventDefault();
                saveCampaignAsTemplate();
            }
        }, true);

        // Test sends and "save as template" both use vPost() (see admin.js):
        // POST as JSON, never navigate, toast the server's own verdict. A
        // full-page redirect here would wipe whatever the admin has typed
        // and not yet saved, the same way an earlier bug once did.
        function sendCampaignTest() {
            if (tinymce.get('v-campaign-body')) tinymce.triggerSave();
            window.vPost('{{ route('admin.email-campaigns.test') }}', {
                subject: document.getElementById('v-campaign-subject').value,
                body: document.getElementById('v-campaign-body').value,
            });
        }

        async function saveCampaignAsTemplate() {
            if (tinymce.get('v-campaign-body')) tinymce.triggerSave();
            var name = document.getElementById('v-save-template-name').value;
            if (!name) {
                document.getElementById('v-save-template-name').reportValidity();
                return;
            }

            var result = await window.vPost('{{ route('admin.email-campaigns.templates.store') }}', {
                name: name,
                subject: document.getElementById('v-campaign-subject').value,
                body: document.getElementById('v-campaign-body').value,
            });

            if (result.ok) {
                document.getElementById('v-save-template-name').value = '';
                window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
            }
        }

        function campaignComposer(config) {
            return {
                audienceType: config.audienceType,
                groupId: config.groupId,
                countsUrl: config.countsUrl,
                audienceTotal: 0,
                willReceive: 0,
                loading: false,
                async fetchCounts() {
                    this.loading = true;
                    var params = new URLSearchParams();
                    params.set('audience_type', this.audienceType);
                    if (this.audienceType === 'group' && this.groupId) {
                        params.set('group_id', this.groupId);
                    }
                    if (this.audienceType === 'selected') {
                        document.querySelectorAll('input[name="customer_ids[]"]:checked').forEach(function (el) {
                            params.append('customer_ids[]', el.value);
                        });
                    }
                    try {
                        var response = await fetch(this.countsUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                        });
                        var data = await response.json();
                        this.audienceTotal = data.audience_total;
                        this.willReceive = data.will_receive;
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-admin.shell>
