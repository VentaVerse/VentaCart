<x-admin3.shell title="Reviews" active="sales">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Reviews</h1>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="reviews" />
        </div>
    </div>

    <div class="v3-tabs" role="tablist" aria-label="Reviews">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
            <a class="v3-tab" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" href="{{ route('admin.reviews.index', ['tab' => $key]) }}">
                {{ $label }}
                <span class="v3-tabs__count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
        <a class="v3-tabs__aside" href="{{ route('admin.reviews.index', ['tab' => 'settings']) }}">Settings <x-admin3.icon name="chevron" /></a>
    </div>

    @if ($tab === 'settings')
        <x-admin3.form :action="route('admin.settings.update')">
            <input type="hidden" name="group" value="reviews">
            <input type="hidden" name="_redirect" value="{{ route('admin.reviews.index', ['tab' => 'settings']) }}">
            <div class="v3-settle">
                <x-admin3.panel title="Review requests"
                    note="review:send mails customers whose orders reached the trigger status, after the delay.">
                    <div>
                        <label class="v3-toggle">
                            <input type="hidden" name="settings[enabled]" value="0">
                            <input type="checkbox" name="settings[enabled]" value="1" @checked(($settings['reviews']['enabled'] ?? '0') === '1')>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            <span>Send review requests automatically</span>
                        </label>
                    </div>
                    <div class="v3-gap v3-grid v3-grid--2">
                        <x-admin3.field name="settings[trigger_status_id]" label="Trigger when order reaches">
                            <select name="settings[trigger_status_id]" class="v3-control">
                                <option value="">Select status…</option>
                                @foreach ($orderStatuses as $status)
                                    <option value="{{ $status->id }}" @selected(($settings['reviews']['trigger_status_id'] ?? '') == $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="settings[trigger_delay_days]" label="Delay (days)">
                            <input type="number" name="settings[trigger_delay_days]" min="0" max="90" class="v3-control"
                                   value="{{ $settings['reviews']['trigger_delay_days'] ?? 7 }}">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap">
                        <x-admin3.field name="settings[review_email_template_id]" label="Email template">
                            <select name="settings[review_email_template_id]" class="v3-control">
                                @foreach ($reviewEmailTemplates as $tmpl)
                                    <option value="{{ $tmpl->id }}" @selected(($settings['reviews']['review_email_template_id'] ?? '') == $tmpl->id)>{{ $tmpl->name }}{{ $tmpl->is_active ? '' : ' (disabled)' }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap">
                        <x-admin3.field name="settings[review_customer_groups]" label="Customer groups"
                            hint="Leave all unchecked to send to every group.">
                            <span></span>
                        </x-admin3.field>
                        @php $checkedGroups = json_decode($settings['reviews']['review_customer_groups'] ?? '[]', true) ?: []; @endphp
                        <div class="v3-settle">
                            @foreach ($customerGroups as $group)
                                <div>
                                    <label class="v3-toggle">
                                        <input type="checkbox" name="settings[review_customer_groups][]" value="{{ $group->id }}" @checked(in_array($group->id, $checkedGroups))>
                                        <span class="v3-toggle__track" aria-hidden="true"></span>
                                        <span>{{ $group->name }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-admin3.panel>

                <x-admin3.panel title="Coupon reward"
                    note="Approving a review issues a one-off coupon and mails it with the review_thankyou template.">
                    <div>
                        <label class="v3-toggle">
                            <input type="hidden" name="settings[coupon_enabled]" value="0">
                            <input type="checkbox" name="settings[coupon_enabled]" value="1" @checked(($settings['reviews']['coupon_enabled'] ?? '0') === '1')>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            <span>Send a coupon after a review is approved</span>
                        </label>
                    </div>
                    <div class="v3-gap v3-grid v3-grid--3">
                        <x-admin3.field name="settings[coupon_type]" label="Discount type">
                            <select name="settings[coupon_type]" class="v3-control">
                                <option value="percent" @selected(($settings['reviews']['coupon_type'] ?? 'percent') === 'percent')>Percentage (%)</option>
                                <option value="fixed" @selected(($settings['reviews']['coupon_type'] ?? '') === 'fixed')>Fixed amount</option>
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="settings[coupon_value]" label="Discount value">
                            <input type="number" name="settings[coupon_value]" min="0" step="0.01" class="v3-control"
                                   value="{{ $settings['reviews']['coupon_value'] ?? 10 }}">
                        </x-admin3.field>
                        <x-admin3.field name="settings[coupon_expiry_days]" label="Expiry (days)">
                            <input type="number" name="settings[coupon_expiry_days]" min="1" max="365" class="v3-control"
                                   value="{{ $settings['reviews']['coupon_expiry_days'] ?? 30 }}">
                        </x-admin3.field>
                    </div>
                </x-admin3.panel>
            </div>
        </x-admin3.form>
    @else
        <div class="v3-settle">
            <form method="GET" action="{{ route('admin.reviews.index') }}" class="v3-filterbar">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="search" value="{{ $search }}" placeholder="Product, customer, or title…"
                       aria-label="Search reviews" class="v3-control v3-filterbar__grow">
                <button type="submit" class="v3-btn v3-btn--secondary">Search</button>
                @if ($search)
                    <a class="v3-link" href="{{ route('admin.reviews.index', ['tab' => $tab]) }}">Clear</a>
                @endif
            </form>

            <x-admin3.panel flush>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Product</th><th>Customer</th><th>Rating</th><th>Title</th><th>Date</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($reviews as $review)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.reviews.show', $review) }}" class="v3-rowlink">{{ $review->product->name ?? 'Deleted product' }}</a>
                                        @if ($review->is_verified_purchase)<span class="v3-badge v3-badge--good">Verified</span>@endif
                                    </td>
                                    <td>
                                        {{ $review->name }}
                                        <span class="v3-cell-quiet v3-block">{{ $review->email }}</span>
                                    </td>
                                    <td>
                                        <span class="v3-stars" aria-label="{{ $review->rating }} of 5"><span class="v3-stars__on">{{ str_repeat('★', (int) $review->rating) }}</span>{{ str_repeat('★', 5 - (int) $review->rating) }}</span>
                                    </td>
                                    <td class="v3-cell-quiet">{{ Str::limit($review->title, 40) ?: '-' }}</td>
                                    <td class="v3-cell-quiet">{{ $review->created_at->format('M j, Y') }}</td>
                                    <td class="v3-cell-act">
                                        @if ($tab === 'pending')
                                            <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                                                @csrf
                                                <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.reviews.reject', $review) }}">
                                                @csrf
                                                <button type="submit" class="v3-btn v3-btn--ghost v3-btn--sm">Reject</button>
                                            </form>
                                        @endif
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-review-{{ $review->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="v3-empty"><p class="v3-empty__title">No {{ $tab }} reviews</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin3.panel>

            <x-admin3.pager :paginator="$reviews" />

            @foreach ($reviews as $review)
                <x-admin3.confirm name="delete-review-{{ $review->id }}" title="Delete review?"
                    message="Delete this review? This cannot be undone." confirm="Delete"
                    :action="route('admin.reviews.destroy', $review)" method="DELETE" />
            @endforeach
        </div>
    @endif
</x-admin3.shell>
