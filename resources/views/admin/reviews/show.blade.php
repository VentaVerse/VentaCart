<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.reviews.index', ['tab' => $review->status]) }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            Review #{{ $review->id }}
        </div>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Review Content --}}
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $review->product->name ?? 'Deleted product' }}</h3>
                    <p class="text-sm text-gray-500 mt-1">by {{ $review->name }} ({{ $review->email }})</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    {{ $review->status === 'approved' ? 'bg-green-100 text-green-800' : ($review->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                    {{ ucfirst($review->status) }}
                </span>
            </div>

            {{-- Star Rating --}}
            <div class="flex items-center gap-1 mb-4">
                <div class="flex gap-0.5 text-amber-400 text-xl">
                    @for ($i = 1; $i <= 5; $i++)
                        @if ($i <= $review->rating)
                            <span>&#9733;</span>
                        @else
                            <span class="text-gray-300">&#9733;</span>
                        @endif
                    @endfor
                </div>
                <span class="text-sm text-gray-500 ml-2">{{ $review->rating }}/5</span>
                @if ($review->is_verified_purchase)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Verified Purchase</span>
                @endif
            </div>

            @if ($review->title)
                <h4 class="text-base font-semibold text-gray-800 mb-2">{{ $review->title }}</h4>
            @endif

            @if ($review->body)
                <p class="text-gray-700 whitespace-pre-wrap">{{ $review->body }}</p>
            @else
                <p class="text-gray-400 italic">No body text.</p>
            @endif

            {{-- Review Images --}}
            @if ($review->images->isNotEmpty())
                <div class="mt-6 border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Attached Photos</h4>
                    <div class="flex gap-3">
                        @foreach ($review->images as $img)
                            <a href="{{ asset('storage/' . $img->path) }}" target="_blank" class="block w-32 h-32 rounded-lg overflow-hidden border border-gray-200">
                                <img src="{{ asset('storage/' . $img->path) }}" alt="Review photo" class="w-full h-full object-cover">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Coupon Info --}}
            @if ($review->coupon)
                <div class="mt-6 border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Coupon Issued</h4>
                    <div class="bg-gray-50 rounded-lg p-3 text-sm">
                        <p><span class="text-gray-500">Code:</span> <span class="font-mono font-semibold text-gray-900">{{ $review->coupon->coupon_code }}</span></p>
                        <p><span class="text-gray-500">Discount:</span> {{ $review->coupon->discount_type === 'percent' ? $review->coupon->discount_value . '%' : '₱' . number_format($review->coupon->discount_value, 2) }}</p>
                        <p><span class="text-gray-500">Expires:</span> {{ $review->coupon->expires_at?->format('M j, Y') ?? 'N/A' }}</p>
                        <p><span class="text-gray-500">Used:</span> {{ $review->coupon->used ? 'Yes' : 'No' }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 h-fit">
            <h4 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Details</h4>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Submitted</dt>
                    <dd class="text-gray-900">{{ $review->created_at->format('M j, Y g:i A') }}</dd>
                </div>
                @if ($review->published_at)
                    <div>
                        <dt class="text-gray-500">Published</dt>
                        <dd class="text-gray-900">{{ $review->published_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if ($review->order_id)
                    <div>
                        <dt class="text-gray-500">Order</dt>
                        <dd><a href="{{ route('admin.orders.show', $review->order_id) }}" class="text-indigo-600 hover:text-indigo-800">#{{ $review->order_id }}</a></dd>
                    </div>
                @endif
            </dl>

            {{-- Actions --}}
            <div class="mt-6 space-y-2">
                @if ($review->status === 'pending')
                    <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.reviews.reject', $review) }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">Reject</button>
                    </form>
                @elseif ($review->status === 'rejected')
                    <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Approve</button>
                    </form>
                @elseif ($review->status === 'approved')
                    <form method="POST" action="{{ route('admin.reviews.reject', $review) }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">Reject</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Delete this review permanently?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full px-4 py-2 bg-white text-red-600 text-sm font-medium rounded-lg border border-red-300 hover:bg-red-50">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
