<x-admin.shell title="Reviews">
    <x-admin.page title="Reviews">
        <div class="space-y-5">
            {{-- Tabs --}}
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter reviews by status">
                @foreach (['pending', 'approved', 'rejected'] as $status)
                    <a href="{{ route('admin.reviews.index', ['tab' => $status, 'search' => request('search')]) }}"
                       class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors
                           {{ $tab === $status ? 'border-v-accent bg-v-accent-weak text-v-accent' : 'border-v-border bg-v-surface text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                        {{ ucfirst($status) }}
                        @if ($counts[$status] > 0)
                            <span class="v-num rounded-full bg-v-sunken px-1.5 text-[11px] {{ $tab === $status ? 'text-v-accent' : 'text-v-muted' }}">{{ $counts[$status] }}</span>
                        @endif
                    </a>
                @endforeach
                <a href="{{ route('admin.reviews.index', ['tab' => 'settings']) }}"
                   class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors
                       {{ $tab === 'settings' ? 'border-v-accent bg-v-accent-weak text-v-accent' : 'border-v-border bg-v-surface text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                    Settings
                </a>
            </div>

            @if ($tab === 'settings')
                {{-- Review Settings --}}
                <x-admin.card>
                    <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form>
                        @csrf
                        <input type="hidden" name="group" value="reviews">

                        <div class="space-y-5">
                            <div>
                                <h3 class="mb-4 text-[13px] font-semibold uppercase tracking-wide text-v-secondary">Review Requests</h3>
                                <div class="max-w-lg space-y-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <x-admin.toggle name="settings[enabled]" :checked="($settings['reviews']['enabled'] ?? '0') == '1'" />
                                        <span class="text-[13px] font-medium text-v-text">Enable automatic review requests</span>
                                    </label>

                                    <x-admin.field name="settings[trigger_status_id]" label="Trigger when order reaches status">
                                        <x-admin.select name="settings[trigger_status_id]" class="max-w-xs">
                                            <option value="">-- Select Status --</option>
                                            @foreach ($orderStatuses as $status)
                                                <option value="{{ $status->id }}" {{ ($settings['reviews']['trigger_status_id'] ?? '') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                            @endforeach
                                        </x-admin.select>
                                    </x-admin.field>

                                    <x-admin.field name="settings[trigger_delay_days]" label="Delay after status change (days)">
                                        <x-admin.input type="number" name="settings[trigger_delay_days]" :value="$settings['reviews']['trigger_delay_days'] ?? 7" min="0" max="90" class="w-32" />
                                    </x-admin.field>

                                    <div>
                                        <p class="mb-2 text-[13px] font-medium text-v-text">Customer groups that receive review requests</p>
                                        <div class="max-h-48 space-y-1.5 overflow-y-auto">
                                            @foreach ($customerGroups as $group)
                                                <x-admin.checkbox name="settings[review_customer_groups][]" :value="$group->id" :label="$group->name"
                                                    :checked="in_array($group->id, json_decode($settings['reviews']['review_customer_groups'] ?? '[]', true) ?: [])" />
                                            @endforeach
                                        </div>
                                        <p class="mt-1 text-xs text-v-muted">Leave all unchecked to send to all customer groups.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-v-border pt-5">
                                <h3 class="mb-4 text-[13px] font-semibold uppercase tracking-wide text-v-secondary">Review Request Email</h3>
                                <div class="max-w-lg">
                                    <x-admin.field name="settings[review_email_template_id]" label="Email template to use"
                                        help="Manage in Email Templates.">
                                        <x-admin.select name="settings[review_email_template_id]">
                                            @foreach ($reviewEmailTemplates as $tmpl)
                                                <option value="{{ $tmpl->id }}" {{ ($settings['reviews']['review_email_template_id'] ?? '') == $tmpl->id ? 'selected' : '' }}>
                                                    {{ $tmpl->name }} {{ $tmpl->is_active ? '' : '(disabled)' }}
                                                </option>
                                            @endforeach
                                        </x-admin.select>
                                    </x-admin.field>
                                    <a href="{{ route('admin.email-templates.index') }}" class="text-xs text-v-accent hover:underline">Manage email templates</a>
                                </div>
                            </div>

                            <div class="border-t border-v-border pt-5">
                                <h3 class="mb-4 text-[13px] font-semibold uppercase tracking-wide text-v-secondary">Coupon Reward</h3>
                                <div class="max-w-lg space-y-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <x-admin.toggle name="settings[coupon_enabled]" :checked="($settings['reviews']['coupon_enabled'] ?? '0') == '1'" />
                                        <span class="text-[13px] font-medium text-v-text">Send coupon after review is approved</span>
                                    </label>

                                    <x-admin.field name="settings[coupon_type]" label="Discount Type">
                                        <x-admin.select name="settings[coupon_type]" class="w-48">
                                            <option value="percent" {{ ($settings['reviews']['coupon_type'] ?? 'percent') === 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                                            <option value="fixed" {{ ($settings['reviews']['coupon_type'] ?? '') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                        </x-admin.select>
                                    </x-admin.field>

                                    <x-admin.field name="settings[coupon_value]" label="Discount Value">
                                        <x-admin.input type="number" name="settings[coupon_value]" :value="$settings['reviews']['coupon_value'] ?? 10" min="0" step="0.01" class="w-32" />
                                    </x-admin.field>

                                    <x-admin.field name="settings[coupon_expiry_days]" label="Coupon Expiry (days)">
                                        <x-admin.input type="number" name="settings[coupon_expiry_days]" :value="$settings['reviews']['coupon_expiry_days'] ?? 30" min="1" max="365" class="w-32" />
                                    </x-admin.field>
                                </div>
                            </div>
                        </div>

                        <x-admin.form-actions>
                            <x-admin.button type="submit">Save Review Settings</x-admin.button>
                        </x-admin.form-actions>
                    </form>
                </x-admin.card>
            @else
                {{-- Search --}}
                <x-admin.card>
                    <form method="GET" action="{{ route('admin.reviews.index') }}" class="flex items-center gap-3">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <div class="flex-1">
                            <x-admin.input name="search" :value="$search" placeholder="Search by product, customer, or title..." />
                        </div>
                        <x-admin.button type="submit">Search</x-admin.button>
                        @if ($search)
                            <a href="{{ route('admin.reviews.index', ['tab' => $tab]) }}" class="text-[13px] text-v-muted hover:text-v-text">Clear</a>
                        @endif
                    </form>
                </x-admin.card>

                {{-- Table --}}
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Product</x-admin.table.th>
                        <x-admin.table.th>Customer</x-admin.table.th>
                        <x-admin.table.th>Rating</x-admin.table.th>
                        <x-admin.table.th>Title</x-admin.table.th>
                        <x-admin.table.th>Date</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot>
                    @forelse ($reviews as $review)
                        <x-admin.table.row :href="route('admin.reviews.show', $review)">
                            <x-admin.table.cell class="text-v-text">
                                {{ $review->product->name ?? 'Deleted product' }}
                                @if ($review->is_verified_purchase)
                                    <x-admin.badge tone="success">Verified</x-admin.badge>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <div class="text-v-text">{{ $review->name }}</div>
                                <div class="text-xs text-v-muted">{{ $review->email }}</div>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <div class="flex gap-0.5 text-v-warning">
                                    @for ($i = 1; $i <= 5; $i++)
                                        @if ($i <= $review->rating)
                                            <span>&#9733;</span>
                                        @else
                                            <span class="text-v-muted">&#9733;</span>
                                        @endif
                                    @endfor
                                </div>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ Str::limit($review->title, 40) ?: '-' }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $review->created_at->format('M j, Y') }}</x-admin.table.cell>
                            <x-admin.table.cell align="right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.reviews.show', $review) }}" class="text-xs font-medium text-v-accent hover:underline">View</a>
                                    @if ($tab === 'pending')
                                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" data-v-form class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-v-accent hover:underline">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" data-v-form class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-v-danger hover:underline">Reject</button>
                                        </form>
                                    @endif
                                    <button type="button" class="text-xs font-medium text-v-muted hover:text-v-danger"
                                        @click="$dispatch('open-modal', 'delete-review-{{ $review->id }}')">Delete</button>
                                </div>
                                <x-admin.confirm-dialog name="delete-review-{{ $review->id }}" title="Delete review"
                                    message="Delete this review? This cannot be undone." confirm="Delete"
                                    :action="route('admin.reviews.destroy', $review)" method="DELETE" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="6" class="px-4 py-2"><x-admin.empty-state icon="reviews" title="No {{ $tab }} reviews found" /></td></tr>
                    @endforelse

                    <x-slot name="footer">
                        @if ($reviews->hasPages())
                            {{ $reviews->links() }}
                        @endif
                    </x-slot>
                </x-admin.table>
            @endif
        </div>
    </x-admin.page>
</x-admin.shell>
