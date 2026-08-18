<x-reviews::space title="Review Settings" active="settings">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Reviews</span>
                <span aria-hidden="true">/</span>
                <span>Settings</span>
            </div>
            <h1 class="v-head__title">Reviews</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="reviews" />
        </div>
    </div>


        <x-admin.form :action="route('admin.settings.update')">
            <input type="hidden" name="group" value="reviews">
            <input type="hidden" name="_redirect" value="{{ route('admin.reviews.settings') }}">
            <div class="v-settle">
                <x-admin.panel title="Review requests"
                    note="review:send mails customers whose orders reached the trigger status, after the delay.">
                    <div>
                        <label class="v-toggle">
                            <input type="hidden" name="settings[enabled]" value="0">
                            <input type="checkbox" name="settings[enabled]" value="1" @checked(($settings['reviews']['enabled'] ?? '0') === '1')>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            <span>Send review requests automatically</span>
                        </label>
                    </div>
                    <div class="v-gap v-grid v-grid--2">
                        <x-admin.field name="settings[trigger_status_id]" label="Trigger when order reaches">
                            <select name="settings[trigger_status_id]" class="v-control">
                                <option value="">Select status…</option>
                                @foreach ($orderStatuses as $status)
                                    <option value="{{ $status->id }}" @selected(($settings['reviews']['trigger_status_id'] ?? '') == $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                        <x-admin.field name="settings[trigger_delay_days]" label="Delay (days)">
                            <input type="number" name="settings[trigger_delay_days]" min="0" max="90" class="v-control"
                                   value="{{ $settings['reviews']['trigger_delay_days'] ?? 7 }}">
                        </x-admin.field>
                    </div>
                    <div class="v-gap">
                        <x-admin.field name="settings[review_email_template_id]" label="Email template">
                            <select name="settings[review_email_template_id]" class="v-control">
                                @foreach ($reviewEmailTemplates as $tmpl)
                                    <option value="{{ $tmpl->id }}" @selected(($settings['reviews']['review_email_template_id'] ?? '') == $tmpl->id)>{{ $tmpl->name }}{{ $tmpl->is_active ? '' : ' (disabled)' }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                    </div>
                    <div class="v-gap">
                        <x-admin.field name="settings[review_customer_groups]" label="Customer groups"
                            hint="Leave all unchecked to send to every group.">
                            <span></span>
                        </x-admin.field>
                        @php $checkedGroups = json_decode($settings['reviews']['review_customer_groups'] ?? '[]', true) ?: []; @endphp
                        <div class="v-settle">
                            @foreach ($customerGroups as $group)
                                <div>
                                    <label class="v-toggle">
                                        <input type="checkbox" name="settings[review_customer_groups][]" value="{{ $group->id }}" @checked(in_array($group->id, $checkedGroups))>
                                        <span class="v-toggle__track" aria-hidden="true"></span>
                                        <span>{{ $group->name }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Coupon reward"
                    note="Approving a review issues a one-off coupon and mails it with the review_thankyou template.">
                    <div>
                        <label class="v-toggle">
                            <input type="hidden" name="settings[coupon_enabled]" value="0">
                            <input type="checkbox" name="settings[coupon_enabled]" value="1" @checked(($settings['reviews']['coupon_enabled'] ?? '0') === '1')>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            <span>Send a coupon after a review is approved</span>
                        </label>
                    </div>
                    <div class="v-gap v-grid v-grid--3">
                        <x-admin.field name="settings[coupon_type]" label="Discount type">
                            <select name="settings[coupon_type]" class="v-control">
                                <option value="percent" @selected(($settings['reviews']['coupon_type'] ?? 'percent') === 'percent')>Percentage (%)</option>
                                <option value="fixed" @selected(($settings['reviews']['coupon_type'] ?? '') === 'fixed')>Fixed amount</option>
                            </select>
                        </x-admin.field>
                        <x-admin.field name="settings[coupon_value]" label="Discount value">
                            <input type="number" name="settings[coupon_value]" min="0" step="0.01" class="v-control"
                                   value="{{ $settings['reviews']['coupon_value'] ?? 10 }}">
                        </x-admin.field>
                        <x-admin.field name="settings[coupon_expiry_days]" label="Expiry (days)">
                            <input type="number" name="settings[coupon_expiry_days]" min="1" max="365" class="v-control"
                                   value="{{ $settings['reviews']['coupon_expiry_days'] ?? 30 }}">
                        </x-admin.field>
                    </div>
                </x-admin.panel>
            </div>
        </x-admin.form>
</x-reviews::space>
