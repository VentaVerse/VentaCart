<x-admin-layout>
    <x-slot name="header">Reviews</x-slot>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        {{-- Tabs --}}
        <div class="border-b border-gray-200">
            <nav class="flex">
                @foreach (['pending', 'approved', 'rejected'] as $status)
                    <a href="{{ route('admin.reviews.index', ['tab' => $status, 'search' => request('search')]) }}"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors {{ $tab === $status ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        {{ ucfirst($status) }}
                        @if ($counts[$status] > 0)
                            <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full {{ $status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">{{ $counts[$status] }}</span>
                        @endif
                    </a>
                @endforeach
                <a href="{{ route('admin.reviews.index', ['tab' => 'settings']) }}"
                    class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors {{ $tab === 'settings' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Settings
                </a>
            </nav>
        </div>

        @if ($tab === 'settings')
            {{-- Review Settings --}}
            <div class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="reviews">

                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Review Requests</h3>
                    <div class="space-y-4 max-w-lg">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[enabled]" value="0">
                            <input type="checkbox" name="settings[enabled]" value="1" id="review-enabled"
                                {{ ($settings['reviews']['enabled'] ?? '0') == '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <label for="review-enabled" class="text-sm font-medium text-gray-700">Enable automatic review requests</label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Trigger when order reaches status</label>
                            <select name="settings[trigger_status_id]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Select Status --</option>
                                @foreach ($orderStatuses as $status)
                                    <option value="{{ $status->id }}" {{ ($settings['reviews']['trigger_status_id'] ?? '') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Delay after status change (days)</label>
                            <input type="number" name="settings[trigger_delay_days]" value="{{ $settings['reviews']['trigger_delay_days'] ?? 7 }}" min="0" max="90"
                                class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Customer groups that receive review requests</label>
                            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                                @foreach ($customerGroups as $group)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" name="settings[review_customer_groups][]" value="{{ $group->id }}"
                                            {{ in_array($group->id, json_decode($settings['reviews']['review_customer_groups'] ?? '[]', true) ?: []) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-indigo-600">
                                        {{ $group->name }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Leave all unchecked to send to all customer groups.</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 mt-6 pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Review Request Email</h3>
                        <div class="space-y-4 max-w-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email template to use</label>
                                <select name="settings[review_email_template_id]" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    @foreach ($reviewEmailTemplates as $tmpl)
                                        <option value="{{ $tmpl->id }}" {{ ($settings['reviews']['review_email_template_id'] ?? '') == $tmpl->id ? 'selected' : '' }}>
                                            {{ $tmpl->name }} {{ $tmpl->is_active ? '' : '(disabled)' }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    <a href="{{ route('admin.email-templates.index') }}" class="text-indigo-600 hover:underline">Manage email templates</a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 mt-8 pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Coupon Reward</h3>
                        <div class="space-y-4 max-w-lg">
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="settings[coupon_enabled]" value="0">
                                <input type="checkbox" name="settings[coupon_enabled]" value="1" id="coupon-enabled"
                                    {{ ($settings['reviews']['coupon_enabled'] ?? '0') == '1' ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <label for="coupon-enabled" class="text-sm font-medium text-gray-700">Send coupon after review is approved</label>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Discount Type</label>
                                <select name="settings[coupon_type]" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="percent" {{ ($settings['reviews']['coupon_type'] ?? 'percent') === 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                                    <option value="fixed" {{ ($settings['reviews']['coupon_type'] ?? '') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Discount Value</label>
                                <input type="number" name="settings[coupon_value]" value="{{ $settings['reviews']['coupon_value'] ?? 10 }}" min="0" step="0.01"
                                    class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Coupon Expiry (days)</label>
                                <input type="number" name="settings[coupon_expiry_days]" value="{{ $settings['reviews']['coupon_expiry_days'] ?? 30 }}" min="1" max="365"
                                    class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Review Settings</button>
                    </div>
                </form>
            </div>
        @else
            {{-- Search --}}
            <div class="p-4 border-b border-gray-200">
                <form method="GET" action="{{ route('admin.reviews.index') }}" class="flex items-center gap-3">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search by product, customer, or title..."
                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Search</button>
                    @if ($search)
                        <a href="{{ route('admin.reviews.index', ['tab' => $tab]) }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
                    @endif
                </form>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($reviews as $review)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ $review->product->name ?? 'Deleted product' }}
                                    @if ($review->is_verified_purchase)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Verified</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <div>{{ $review->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $review->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex gap-0.5 text-amber-400">
                                        @for ($i = 1; $i <= 5; $i++)
                                            @if ($i <= $review->rating)
                                                <span>&#9733;</span>
                                            @else
                                                <span class="text-gray-300">&#9733;</span>
                                            @endif
                                        @endfor
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ Str::limit($review->title, 40) ?: '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $review->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.reviews.show', $review) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View</a>
                                        @if ($tab === 'pending')
                                            <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-green-600 hover:text-green-800 text-xs font-medium">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Reject</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="inline" onsubmit="return confirm('Delete this review?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-gray-400 hover:text-red-600 text-xs font-medium">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No {{ $tab }} reviews found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($reviews->hasPages())
                <div class="p-4 border-t border-gray-200">
                    {{ $reviews->links() }}
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
