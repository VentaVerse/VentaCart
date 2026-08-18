<x-admin.shell title="Coupons">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.extensions.order-totals') }}">Order Totals</a>
                <span aria-hidden="true">/</span>
                <span>Coupons</span>
            </div>
            <h1 class="v-head__title">Coupons</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.coupons.settings') }}" class="v-btn v-btn--secondary">Settings</a>
            <a href="{{ route('admin.coupons.create') }}" class="v-btn v-btn--primary">Add coupon</a>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.coupons.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Code or name…"
                   aria-label="Search coupons" class="v-control v-filterbar__grow">
            <select name="type" aria-label="Filter by type" class="v-control v-w-name">
                <option value="">All types</option>
                <option value="percent" @selected(request('type') === 'percent')>Percent</option>
                <option value="fixed" @selected(request('type') === 'fixed')>Fixed</option>
                <option value="free_shipping" @selected(request('type') === 'free_shipping')>Free shipping</option>
            </select>
            <select name="status" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                <option value="expired" @selected(request('status') === 'expired')>Expired</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'type', 'status']))
                <a href="{{ route('admin.coupons.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th class="v-cell-num">Discount</th>
                            <th class="v-cell-num">Usage</th>
                            <th>Status</th>
                            <th>Dates</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coupons as $coupon)
                            <tr>
                                <td><a href="{{ route('admin.coupons.edit', $coupon) }}" class="v-rowlink v-mono">{{ $coupon->code }}</a></td>
                                <td>{{ $coupon->name }}</td>
                                <td>
                                    <span class="v-badge v-badge--{{ ['percent' => 'info', 'fixed' => 'info', 'free_shipping' => 'good'][$coupon->type] ?? 'quiet' }}">
                                        {{ ['percent' => 'Percent', 'fixed' => 'Fixed', 'free_shipping' => 'Free shipping'][$coupon->type] ?? $coupon->type }}
                                    </span>
                                </td>
                                <td class="v-cell-num v-mono">
                                    @if ($coupon->type === 'percent'){{ rtrim(rtrim(number_format($coupon->discount_value, 2), '0'), '.') }}%
                                    @elseif ($coupon->type === 'fixed'){{ number_format($coupon->discount_value, 2) }}
                                    @else -
                                    @endif
                                </td>
                                <td class="v-cell-num v-mono">{{ $coupon->times_used }}@if ($coupon->usage_limit) / {{ $coupon->usage_limit }}@endif</td>
                                <td>
                                    @if ($coupon->is_expired)
                                        <span class="v-badge v-badge--quiet">Expired</span>
                                    @elseif ($coupon->is_active)
                                        <span class="v-badge v-badge--good">Active</span>
                                    @else
                                        <span class="v-badge v-badge--warn">Inactive</span>
                                    @endif
                                </td>
                                <td class="v-cell-quiet">
                                    @if ($coupon->date_start || $coupon->date_end)
                                        {{ $coupon->date_start?->format('M d, Y') ?? 'Any' }} to {{ $coupon->date_end?->format('M d, Y') ?? 'any' }}
                                    @else
                                        Always
                                    @endif
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-coupon-{{ $coupon->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No coupons found</p>
                                        <p class="v-empty__line">{{ request()->anyFilled(['search', 'type', 'status']) ? 'Try adjusting the filters.' : 'Discount codes customers apply at checkout live here.' }}</p>
                                        @unless (request()->anyFilled(['search', 'type', 'status']))
                                            <a href="{{ route('admin.coupons.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first coupon</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$coupons" />

        @foreach ($coupons as $coupon)
            <x-admin.confirm name="delete-coupon-{{ $coupon->id }}" title="Delete {{ $coupon->code }}?"
                message="This permanently removes the coupon and its usage history. This cannot be undone."
                confirm="Delete" :action="route('admin.coupons.destroy', $coupon)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
