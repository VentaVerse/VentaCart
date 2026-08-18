<x-reviews::space title="Reviews" :active="$tab" :counts="$counts">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Reviews</h1>
        </div>
    </div>


        <div class="v-settle">
            <form method="GET" action="{{ route('admin.reviews.index') }}" class="v-filterbar">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="search" value="{{ $search }}" placeholder="Product, customer, or title…"
                       aria-label="Search reviews" class="v-control v-filterbar__grow">
                <button type="submit" class="v-btn v-btn--secondary">Search</button>
                @if ($search)
                    <a class="v-link" href="{{ route('admin.reviews.index', ['tab' => $tab]) }}">Clear</a>
                @endif
            </form>

            <x-admin.panel flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Product</th><th>Customer</th><th>Rating</th><th>Title</th><th>Date</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($reviews as $review)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.reviews.show', $review) }}" class="v-rowlink">{{ $review->product->name ?? 'Deleted product' }}</a>
                                        @if ($review->is_verified_purchase)<span class="v-badge v-badge--good">Verified</span>@endif
                                    </td>
                                    <td>
                                        {{ $review->name }}
                                        <span class="v-cell-quiet v-block">{{ $review->email }}</span>
                                    </td>
                                    <td>
                                        <span class="v-stars" aria-label="{{ $review->rating }} of 5"><span class="v-stars__on">{{ str_repeat('★', (int) $review->rating) }}</span>{{ str_repeat('★', 5 - (int) $review->rating) }}</span>
                                    </td>
                                    <td class="v-cell-quiet">{{ Str::limit($review->title, 40) ?: '-' }}</td>
                                    <td class="v-cell-quiet">{{ $review->created_at->format('M j, Y') }}</td>
                                    <td class="v-cell-act">
                                        @if ($tab === 'pending')
                                            <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                                                @csrf
                                                <button type="submit" class="v-btn v-btn--secondary v-btn--sm">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.reviews.reject', $review) }}">
                                                @csrf
                                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm">Reject</button>
                                            </form>
                                        @endif
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-review-{{ $review->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="v-empty"><p class="v-empty__title">No {{ $tab }} reviews</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>

            <x-admin.pager :paginator="$reviews" />

            @foreach ($reviews as $review)
                <x-admin.confirm name="delete-review-{{ $review->id }}" title="Delete review?"
                    message="Delete this review? This cannot be undone." confirm="Delete"
                    :action="route('admin.reviews.destroy', $review)" method="DELETE" />
            @endforeach
        </div>
</x-reviews::space>
