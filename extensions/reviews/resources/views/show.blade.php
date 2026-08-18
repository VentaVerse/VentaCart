<x-reviews::space :title="'Review #' . $review->id" :active="$review->status">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.reviews.index', ['tab' => $review->status]) }}">Reviews</a>
                <span aria-hidden="true">/</span>
                <span>Review #{{ $review->id }}</span>
            </div>
            <h1 class="v-head__title">{{ $review->product->name ?? 'Deleted product' }}</h1>
            <p class="v-head__standing">by {{ $review->name }}{{ $review->email ? ' (' . $review->email . ')' : '' }}</p>
        </div>
        <div class="v-head__actions">
            @if ($review->status !== 'approved')
                <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                    @csrf
                    <button type="submit" class="v-btn v-btn--primary">Approve</button>
                </form>
            @endif
            @if ($review->status !== 'rejected')
                <form method="POST" action="{{ route('admin.reviews.reject', $review) }}">
                    @csrf
                    <button type="submit" class="v-btn v-btn--secondary">Reject</button>
                </form>
            @endif
            <button type="button" class="v-btn v-btn--danger" @click="$dispatch('v-confirm', 'delete-review')">Delete</button>
        </div>
    </div>

    <div class="v-editcols">
        <div class="v-settle">
            <x-admin.panel :title="$review->title ?: 'Review'">
                <div class="v-actrow">
                    <span class="v-stars" aria-label="{{ $review->rating }} of 5"><span class="v-stars__on">{{ str_repeat('★', (int) $review->rating) }}</span>{{ str_repeat('★', 5 - (int) $review->rating) }}</span>
                    <span class="v-cell-quiet">{{ $review->rating }}/5</span>
                    @if ($review->is_verified_purchase)<span class="v-badge v-badge--good">Verified purchase</span>@endif
                </div>
                <div class="v-gap">
                    @if ($review->body)
                        <p style="white-space: pre-wrap">{{ $review->body }}</p>
                    @else
                        <p class="v-cell-quiet">No body text.</p>
                    @endif
                </div>
                @if ($review->images->isNotEmpty())
                    <div class="v-gap v-actrow">
                        @foreach ($review->images as $img)
                            <a href="{{ storage_url($img->path) }}" target="_blank">
                                <img src="{{ storage_url($img->path) }}" alt="Review photo" style="width: 84px; height: 84px; object-fit: cover; border-radius: 8px;">
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-admin.panel>

            @if ($review->coupon)
                <x-admin.panel title="Coupon issued">
                    <dl class="v-facts">
                        <dt>Code</dt><dd class="v-mono">{{ $review->coupon->coupon_code }}</dd>
                        <dt>Discount</dt><dd>{{ $review->coupon->discount_type === 'percent' ? $review->coupon->discount_value . '%' : '₱' . number_format($review->coupon->discount_value, 2) }}</dd>
                        <dt>Expires</dt><dd>{{ $review->coupon->expires_at?->format('M j, Y') ?? 'Never' }}</dd>
                        <dt>Used</dt><dd>{{ $review->coupon->used ? 'Yes' : 'No' }}</dd>
                    </dl>
                </x-admin.panel>
            @endif
        </div>

        <div class="v-settle">
            <x-admin.panel title="Details">
                <dl class="v-facts">
                    <dt>Status</dt>
                    <dd><span class="v-badge {{ $review->status === 'approved' ? 'v-badge--good' : ($review->status === 'rejected' ? 'v-badge--bad' : 'v-badge--warn') }}">{{ ucfirst($review->status) }}</span></dd>
                    <dt>Submitted</dt><dd>{{ $review->created_at->format('M j, Y g:i A') }}</dd>
                    @if ($review->published_at)
                        <dt>Published</dt><dd>{{ $review->published_at->format('M j, Y g:i A') }}</dd>
                    @endif
                    @if ($review->order_id)
                        <dt>Order</dt><dd><a href="{{ route('admin.orders.show', $review->order_id) }}" class="v-link v-mono">#{{ $review->order_id }}</a></dd>
                    @endif
                </dl>
            </x-admin.panel>
        </div>
    </div>

    <x-admin.confirm name="delete-review" title="Delete review?"
        message="Delete this review permanently? This cannot be undone." confirm="Delete"
        :action="route('admin.reviews.destroy', $review)" method="DELETE" />
</x-reviews::space>
