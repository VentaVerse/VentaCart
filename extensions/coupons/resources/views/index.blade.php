<x-admin3.shell title="Coupons">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.extensions.order-totals') }}">Order Totals</a>
                <span aria-hidden="true">/</span>
                <span>Coupons</span>
            </div>
            <h1 class="v3-head__title">Coupons</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.coupons.settings') }}" class="v3-btn v3-btn--secondary">Settings</a>
            <a href="{{ route('admin.coupons.create') }}" class="v3-btn v3-btn--primary">Add coupon</a>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.coupons.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Code or name…"
                   aria-label="Search coupons" class="v3-control v3-filterbar__grow">
            <select name="type" aria-label="Filter by type" class="v3-control v3-w-name">
                <option value="">All types</option>
                <option value="percent" @selected(request('type') === 'percent')>Percent</option>
                <option value="fixed" @selected(request('type') === 'fixed')>Fixed</option>
                <option value="free_shipping" @selected(request('type') === 'free_shipping')>Free shipping</option>
            </select>
            <select name="status" aria-label="Filter by status" class="v3-control v3-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                <option value="expired" @selected(request('status') === 'expired')>Expired</option>
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'type', 'status']))
                <a href="{{ route('admin.coupons.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th class="v3-cell-num">Discount</th>
                            <th class="v3-cell-num">Usage</th>
                            <th>Status</th>
                            <th>Dates</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coupons as $coupon)
                            <tr>
                                <td><a href="{{ route('admin.coupons.edit', $coupon) }}" class="v3-rowlink v3-mono">{{ $coupon->code }}</a></td>
                                <td>{{ $coupon->name }}</td>
                                <td>
                                    <span class="v3-badge v3-badge--{{ ['percent' => 'info', 'fixed' => 'info', 'free_shipping' => 'good'][$coupon->type] ?? 'quiet' }}">
                                        {{ ['percent' => 'Percent', 'fixed' => 'Fixed', 'free_shipping' => 'Free shipping'][$coupon->type] ?? $coupon->type }}
                                    </span>
                                </td>
                                <td class="v3-cell-num v3-mono">
                                    @if ($coupon->type === 'percent'){{ rtrim(rtrim(number_format($coupon->discount_value, 2), '0'), '.') }}%
                                    @elseif ($coupon->type === 'fixed'){{ number_format($coupon->discount_value, 2) }}
                                    @else -
                                    @endif
                                </td>
                                <td class="v3-cell-num v3-mono">{{ $coupon->times_used }}@if ($coupon->usage_limit) / {{ $coupon->usage_limit }}@endif</td>
                                <td>
                                    @if ($coupon->is_expired)
                                        <span class="v3-badge v3-badge--quiet">Expired</span>
                                    @elseif ($coupon->is_active)
                                        <span class="v3-badge v3-badge--good">Active</span>
                                    @else
                                        <span class="v3-badge v3-badge--warn">Inactive</span>
                                    @endif
                                </td>
                                <td class="v3-cell-quiet">
                                    @if ($coupon->date_start || $coupon->date_end)
                                        {{ $coupon->date_start?->format('M d, Y') ?? 'Any' }} to {{ $coupon->date_end?->format('M d, Y') ?? 'any' }}
                                    @else
                                        Always
                                    @endif
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-coupon-{{ $coupon->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No coupons found</p>
                                        <p class="v3-empty__line">{{ request()->anyFilled(['search', 'type', 'status']) ? 'Try adjusting the filters.' : 'Discount codes customers apply at checkout live here.' }}</p>
                                        @unless (request()->anyFilled(['search', 'type', 'status']))
                                            <a href="{{ route('admin.coupons.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create your first coupon</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$coupons" />

        @foreach ($coupons as $coupon)
            <x-admin3.confirm name="delete-coupon-{{ $coupon->id }}" title="Delete {{ $coupon->code }}?"
                message="This permanently removes the coupon and its usage history. This cannot be undone."
                confirm="Delete" :action="route('admin.coupons.destroy', $coupon)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
