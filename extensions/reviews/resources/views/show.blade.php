<x-admin3.shell :title="'Review #' . $review->id" active="sales">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.reviews.index', ['tab' => $review->status]) }}">Reviews</a>
                <span aria-hidden="true">/</span>
                <span>Review #{{ $review->id }}</span>
            </div>
            <h1 class="v3-head__title">{{ $review->product->name ?? 'Deleted product' }}</h1>
            <p class="v3-head__standing">by {{ $review->name }}{{ $review->email ? ' (' . $review->email . ')' : '' }}</p>
        </div>
        <div class="v3-head__actions">
            @if ($review->status !== 'approved')
                <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                    @csrf
                    <button type="submit" class="v3-btn v3-btn--primary">Approve</button>
                </form>
            @endif
            @if ($review->status !== 'rejected')
                <form method="POST" action="{{ route('admin.reviews.reject', $review) }}">
                    @csrf
                    <button type="submit" class="v3-btn v3-btn--secondary">Reject</button>
                </form>
            @endif
            <button type="button" class="v3-btn v3-btn--danger" @click="$dispatch('v3-confirm', 'delete-review')">Delete</button>
        </div>
    </div>

    <div class="v3-editcols">
        <div class="v3-settle">
            <x-admin3.panel :title="$review->title ?: 'Review'">
                <div class="v3-actrow">
                    <span class="v3-stars" aria-label="{{ $review->rating }} of 5"><span class="v3-stars__on">{{ str_repeat('★', (int) $review->rating) }}</span>{{ str_repeat('★', 5 - (int) $review->rating) }}</span>
                    <span class="v3-cell-quiet">{{ $review->rating }}/5</span>
                    @if ($review->is_verified_purchase)<span class="v3-badge v3-badge--good">Verified purchase</span>@endif
                </div>
                <div class="v3-gap">
                    @if ($review->body)
                        <p style="white-space: pre-wrap">{{ $review->body }}</p>
                    @else
                        <p class="v3-cell-quiet">No body text.</p>
                    @endif
                </div>
                @if ($review->images->isNotEmpty())
                    <div class="v3-gap v3-actrow">
                        @foreach ($review->images as $img)
                            <a href="{{ storage_url($img->path) }}" target="_blank">
                                <img src="{{ storage_url($img->path) }}" alt="Review photo" style="width: 84px; height: 84px; object-fit: cover; border-radius: 8px;">
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-admin3.panel>

            @if ($review->coupon)
                <x-admin3.panel title="Coupon issued">
                    <dl class="v3-facts">
                        <dt>Code</dt><dd class="v3-mono">{{ $review->coupon->coupon_code }}</dd>
                        <dt>Discount</dt><dd>{{ $review->coupon->discount_type === 'percent' ? $review->coupon->discount_value . '%' : '₱' . number_format($review->coupon->discount_value, 2) }}</dd>
                        <dt>Expires</dt><dd>{{ $review->coupon->expires_at?->format('M j, Y') ?? 'Never' }}</dd>
                        <dt>Used</dt><dd>{{ $review->coupon->used ? 'Yes' : 'No' }}</dd>
                    </dl>
                </x-admin3.panel>
            @endif
        </div>

        <div class="v3-settle">
            <x-admin3.panel title="Details">
                <dl class="v3-facts">
                    <dt>Status</dt>
                    <dd><span class="v3-badge {{ $review->status === 'approved' ? 'v3-badge--good' : ($review->status === 'rejected' ? 'v3-badge--bad' : 'v3-badge--warn') }}">{{ ucfirst($review->status) }}</span></dd>
                    <dt>Submitted</dt><dd>{{ $review->created_at->format('M j, Y g:i A') }}</dd>
                    @if ($review->published_at)
                        <dt>Published</dt><dd>{{ $review->published_at->format('M j, Y g:i A') }}</dd>
                    @endif
                    @if ($review->order_id)
                        <dt>Order</dt><dd><a href="{{ route('admin.orders.show', $review->order_id) }}" class="v3-link v3-mono">#{{ $review->order_id }}</a></dd>
                    @endif
                </dl>
            </x-admin3.panel>
        </div>
    </div>

    <x-admin3.confirm name="delete-review" title="Delete review?"
        message="Delete this review permanently? This cannot be undone." confirm="Delete"
        :action="route('admin.reviews.destroy', $review)" method="DELETE" />
</x-admin3.shell>
