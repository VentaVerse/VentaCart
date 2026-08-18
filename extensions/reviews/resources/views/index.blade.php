<x-admin3.shell title="Reviews" active="sales">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Reviews</h1>
        </div>
    </div>

    <div class="v3-tabs" role="tablist" aria-label="Reviews">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
            <a class="v3-tab" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" href="{{ route('admin.reviews.index', ['tab' => $key]) }}">
                {{ $label }}
                <span class="v3-tabs__count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
        <a class="v3-tabs__aside" href="{{ route('admin.reviews.settings') }}">Settings <x-admin3.icon name="chevron" /></a>
    </div>

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
</x-admin3.shell>
