<x-admin.shell title="Review #{{ $review->id }}">
    <x-admin.page title="Review #{{ $review->id }}"
        :breadcrumb="[['label' => 'Reviews', 'url' => route('admin.reviews.index', ['tab' => $review->status])]]">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Review Content --}}
            <x-admin.card class="lg:col-span-2">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h3 class="text-[15px] font-semibold text-v-text">{{ $review->product->name ?? 'Deleted product' }}</h3>
                        <p class="mt-1 text-[13px] text-v-secondary">by {{ $review->name }} ({{ $review->email }})</p>
                    </div>
                    <x-admin.badge :tone="$review->status === 'approved' ? 'success' : ($review->status === 'rejected' ? 'danger' : 'warning')">
                        {{ ucfirst($review->status) }}
                    </x-admin.badge>
                </div>

                {{-- Star Rating --}}
                <div class="mb-4 flex items-center gap-1">
                    <div class="flex gap-0.5" aria-hidden="true">
                        @for ($i = 1; $i <= 5; $i++)
                            <x-admin.icon name="reviews" :filled="$i <= $review->rating"
                                class="w-5 h-5 {{ $i <= $review->rating ? 'text-v-warning' : 'text-v-muted' }}" />
                        @endfor
                    </div>
                    <span class="ml-2 text-[13px] text-v-secondary">{{ $review->rating }}/5</span>
                    @if ($review->is_verified_purchase)
                        <x-admin.badge tone="success" class="ml-2">Verified Purchase</x-admin.badge>
                    @endif
                </div>

                @if ($review->title)
                    <h4 class="mb-2 text-[14px] font-semibold text-v-text">{{ $review->title }}</h4>
                @endif

                @if ($review->body)
                    <p class="whitespace-pre-wrap text-[13px] text-v-secondary">{{ $review->body }}</p>
                @else
                    <p class="text-[13px] italic text-v-muted">No body text.</p>
                @endif

                {{-- Review Images --}}
                @if ($review->images->isNotEmpty())
                    <div class="mt-6 border-t border-v-border pt-4">
                        <h4 class="mb-3 v-subhead">Attached Photos</h4>
                        <div class="v-tiles v-tiles--sm">
                            @foreach ($review->images as $img)
                                <a href="{{ storage_url($img->path) }}" target="_blank" class="v-tile">
                                    <span class="v-tile__art"><img src="{{ storage_url($img->path) }}" alt="Review photo" class="v-tile__img"></span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Coupon Info --}}
                @if ($review->coupon)
                    <div class="mt-6 border-t border-v-border pt-4">
                        <h4 class="mb-2 v-subhead">Coupon Issued</h4>
                        <dl class="v-facts v-facts--flush max-w-sm">
                            <dt class="v-label">Code</dt>
                            <dd><span class="v-ref">{{ $review->coupon->coupon_code }}</span></dd>
                            <dt class="v-label">Discount</dt>
                            <dd>{{ $review->coupon->discount_type === 'percent' ? $review->coupon->discount_value . '%' : '₱' . number_format($review->coupon->discount_value, 2) }}</dd>
                            <dt class="v-label">Expires</dt>
                            <dd>{{ $review->coupon->expires_at?->format('M j, Y') ?? 'N/A' }}</dd>
                            <dt class="v-label">Used</dt>
                            <dd>{{ $review->coupon->used ? 'Yes' : 'No' }}</dd>
                        </dl>
                    </div>
                @endif
            </x-admin.card>

            {{-- Sidebar --}}
            <x-admin.panel title="Details" id="review-details" class="h-fit" flush>
                <dl class="v-facts">
                    <dt class="v-label">Submitted</dt>
                    <dd>{{ $review->created_at->format('M j, Y g:i A') }}</dd>
                    @if ($review->published_at)
                        <dt class="v-label">Published</dt>
                        <dd>{{ $review->published_at->format('M j, Y g:i A') }}</dd>
                    @endif
                    @if ($review->order_id)
                        <dt class="v-label">Order</dt>
                        <dd><a href="{{ route('admin.orders.show', $review->order_id) }}" class="v-ref">#{{ $review->order_id }}</a></dd>
                    @endif
                </dl>

                {{-- Actions --}}
                <div class="v-panel__body space-y-2">
                    @if ($review->status === 'pending')
                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" data-v-form>
                            @csrf
                            <x-admin.button type="submit" class="w-full" variant="primary">Approve</x-admin.button>
                        </form>
                        <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" data-v-form>
                            @csrf
                            <x-admin.button type="submit" class="w-full" variant="danger">Reject</x-admin.button>
                        </form>
                    @elseif ($review->status === 'rejected')
                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" data-v-form>
                            @csrf
                            <x-admin.button type="submit" class="w-full" variant="primary">Approve</x-admin.button>
                        </form>
                    @elseif ($review->status === 'approved')
                        <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" data-v-form>
                            @csrf
                            <x-admin.button type="submit" class="w-full" variant="danger">Reject</x-admin.button>
                        </form>
                    @endif
                    <x-admin.button type="button" variant="secondary" class="w-full"
                        @click="$dispatch('open-modal', 'delete-review')">
                        Delete
                    </x-admin.button>
                    <x-admin.confirm-dialog name="delete-review" title="Delete review"
                        message="Delete this review permanently? This cannot be undone." confirm="Delete"
                        :action="route('admin.reviews.destroy', $review)" method="DELETE" />
                </div>
            </x-admin.panel>
        </div>
    </x-admin.page>
</x-admin.shell>
