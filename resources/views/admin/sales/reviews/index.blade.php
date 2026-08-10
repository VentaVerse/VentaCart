<x-admin.shell title="Reviews">
    <x-admin.page title="Reviews">
        <div>
            <x-admin.segment label="Filter reviews by status">
                @foreach (['pending', 'approved', 'rejected'] as $status)
                    <x-admin.segment.item :href="route('admin.reviews.index', ['tab' => $status, 'search' => request('search')])"
                        :active="$tab === $status" :count="$counts[$status] > 0 ? $counts[$status] : null">{{ ucfirst($status) }}</x-admin.segment.item>
                @endforeach
                <x-admin.segment.item :href="route('admin.reviews.index', ['tab' => 'settings'])" :active="$tab === 'settings'">Settings</x-admin.segment.item>
            </x-admin.segment>

            @if ($tab === 'settings')
                {{-- Review Settings --}}
                <x-admin.card>
                    <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form>
                        @csrf
                        <input type="hidden" name="group" value="reviews">

                        <div class="space-y-5">
                            <div>
                                <h3 class="mb-4 v-subhead">Review Requests</h3>
                                <div class="max-w-lg space-y-4">
                                    <x-admin.toggle name="settings[enabled]" :checked="($settings['reviews']['enabled'] ?? '0') == '1'" label="Enable automatic review requests" />

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

                                    <div class="v-field">
                                        <span class="v-field__label v-label">
                                            Customer groups that receive review requests
                                            <x-admin.hint label="What an empty selection means">Leave all unchecked to send to all customer groups.</x-admin.hint>
                                        </span>
                                        <div class="v-scroll max-h-48 space-y-1.5 overflow-y-auto">
                                            @foreach ($customerGroups as $group)
                                                <x-admin.checkbox name="settings[review_customer_groups][]" :value="$group->id" :label="$group->name"
                                                    :checked="in_array($group->id, json_decode($settings['reviews']['review_customer_groups'] ?? '[]', true) ?: [])" />
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-v-border pt-5">
                                <h3 class="mb-4 v-subhead">Review Request Email</h3>
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
                                <h3 class="mb-4 v-subhead">Coupon Reward</h3>
                                <div class="max-w-lg space-y-4">
                                    <x-admin.toggle name="settings[coupon_enabled]" :checked="($settings['reviews']['coupon_enabled'] ?? '0') == '1'" label="Send coupon after review is approved" />

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
                <x-admin.filters :action="route('admin.reviews.index')">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <x-admin.input name="search" :value="$search" placeholder="Search by product, customer, or title..." aria-label="Search reviews" class="v-filters__search" />
                    <x-admin.button type="submit" variant="secondary">Search</x-admin.button>
                    @if ($search)
                        <a href="{{ route('admin.reviews.index', ['tab' => $tab]) }}" class="v-filters__reset">Clear</a>
                    @endif
                </x-admin.filters>

                {{-- Table --}}
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Product</x-admin.table.th>
                        <x-admin.table.th>Customer</x-admin.table.th>
                        <x-admin.table.th>Rating</x-admin.table.th>
                        <x-admin.table.th>Title</x-admin.table.th>
                        <x-admin.table.th>Date</x-admin.table.th>
                        <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
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
                                <span class="v-lead">{{ $review->name }}</span>
                                <span class="v-sub">{{ $review->email }}</span>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <div class="flex items-center gap-1.5">
                                    <div class="flex gap-0.5" aria-hidden="true">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <x-admin.icon name="reviews" :filled="$i <= $review->rating"
                                                class="w-3.5 h-3.5 {{ $i <= $review->rating ? 'text-v-warning' : 'text-v-muted' }}" />
                                        @endfor
                                    </div>
                                    <span class="text-xs text-v-secondary">{{ $review->rating }}/5</span>
                                </div>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ Str::limit($review->title, 40) ?: '-' }}</x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $review->created_at->format('M j, Y') }}</x-admin.table.cell>
                            <x-admin.table.cell class="v-col-act">
                                <div class="v-rowactions v-rowactions--end">
                                    <x-admin.button size="sm" variant="ghost" :href="route('admin.reviews.show', $review)">View</x-admin.button>
                                    @if ($tab === 'pending')
                                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" data-v-form>
                                            @csrf
                                            <x-admin.button size="sm" variant="secondary" type="submit">Approve</x-admin.button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" data-v-form>
                                            @csrf
                                            <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="submit">Reject</x-admin.button>
                                        </form>
                                    @endif
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger"
                                        @click="$dispatch('open-modal', 'delete-review-{{ $review->id }}')">Delete</x-admin.button>
                                </div>
                                <x-admin.confirm-dialog name="delete-review-{{ $review->id }}" title="Delete review"
                                    message="Delete this review? This cannot be undone." confirm="Delete"
                                    :action="route('admin.reviews.destroy', $review)" method="DELETE" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr><td colspan="6"><x-admin.empty-state title="No {{ $tab }} reviews found" /></td></tr>
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
