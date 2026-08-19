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
         x-data="campaignComposer(@json(['audienceType' => $initialAudienceType, 'groupId' => $initialGroupId, 'countsUrl' => route('admin.email-campaigns.recipient-count')]))"
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

            <fieldset @disabled($locked) class="v-settle">
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
                                    <option value="{{ $type }}">{{ $label }}</option>
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
                    <button type="submit" name="action" value="draft" class="v-btn v-btn--secondary">Save draft</button>
                    <button type="submit" name="action" value="send" class="v-btn v-btn--primary"
                            onclick="return confirm('Send this campaign now? This cannot be undone.')">Send now</button>
                    <a href="{{ route('admin.email-campaigns.index') }}" class="v-btn v-btn--ghost">Cancel</a>
                </div>
            @endunless
        </form>

        <x-admin.panel title="Test send">
            <form method="POST" action="{{ route('admin.email-campaigns.test') }}"
                  onsubmit="return prepareCampaignTestSend()" class="v-actrow">
                @csrf
                <input type="hidden" name="subject" id="v-campaign-test-subject">
                <input type="hidden" name="body" id="v-campaign-test-body">
                <button type="submit" class="v-btn v-btn--secondary">Send test to me</button>
            </form>
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

        function prepareCampaignTestSend() {
            if (tinymce.get('v-campaign-body')) tinymce.triggerSave();
            document.getElementById('v-campaign-test-subject').value = document.getElementById('v-campaign-subject').value;
            document.getElementById('v-campaign-test-body').value = document.getElementById('v-campaign-body').value;
            return true;
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
