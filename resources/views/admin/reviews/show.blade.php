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
                    <div class="flex gap-0.5 text-xl text-v-warning">
                        @for ($i = 1; $i <= 5; $i++)
                            @if ($i <= $review->rating)
                                <span>&#9733;</span>
                            @else
                                <span class="text-v-muted">&#9733;</span>
                            @endif
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
                        <h4 class="mb-3 text-[13px] font-medium text-v-text">Attached Photos</h4>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($review->images as $img)
                                <a href="{{ storage_url($img->path) }}" target="_blank" class="block h-32 w-32 overflow-hidden rounded-v-md border border-v-border">
                                    <img src="{{ storage_url($img->path) }}" alt="Review photo" class="h-full w-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Coupon Info --}}
                @if ($review->coupon)
                    <div class="mt-6 border-t border-v-border pt-4">
                        <h4 class="mb-2 text-[13px] font-medium text-v-text">Coupon Issued</h4>
                        <div class="rounded-v-md bg-v-sunken p-3 text-[13px] space-y-1">
                            <p><span class="text-v-muted">Code:</span> <span class="v-num font-semibold text-v-text">{{ $review->coupon->coupon_code }}</span></p>
                            <p><span class="text-v-muted">Discount:</span> {{ $review->coupon->discount_type === 'percent' ? $review->coupon->discount_value . '%' : '₱' . number_format($review->coupon->discount_value, 2) }}</p>
                            <p><span class="text-v-muted">Expires:</span> {{ $review->coupon->expires_at?->format('M j, Y') ?? 'N/A' }}</p>
                            <p><span class="text-v-muted">Used:</span> {{ $review->coupon->used ? 'Yes' : 'No' }}</p>
                        </div>
                    </div>
                @endif
            </x-admin.card>

            {{-- Sidebar --}}
            <x-admin.card title="Details" class="h-fit">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-v-muted">Submitted</dt>
                        <dd class="text-[13px] text-v-text">{{ $review->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    @if ($review->published_at)
                        <div>
                            <dt class="text-xs text-v-muted">Published</dt>
                            <dd class="text-[13px] text-v-text">{{ $review->published_at->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif
                    @if ($review->order_id)
                        <div>
                            <dt class="text-xs text-v-muted">Order</dt>
                            <dd><a href="{{ route('admin.orders.show', $review->order_id) }}" class="text-v-accent hover:underline">#{{ $review->order_id }}</a></dd>
                        </div>
                    @endif
                </dl>

                {{-- Actions --}}
                <div class="mt-6 space-y-2">
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
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
