<x-store-layout title="Write a Review">
    <div class="container-store py-12">
        <div class="max-w-xl mx-auto">
            {{-- Product Info --}}
            <div class="text-center mb-8">
                @php $productImage = $review->product?->images->first(); @endphp
                @if ($productImage)
                    <div class="w-24 h-24 mx-auto mb-4 rounded-lg overflow-hidden bg-[var(--color-search-bg)]">
                        <img src="{{ storage_url($productImage->path) }}" alt="{{ $review->product?->name }}" class="w-full h-full object-contain">
                    </div>
                @endif
                <h1 class="text-2xl mb-2" class="font-heading-wide">WRITE A REVIEW</h1>
                <p class="text-[var(--color-text-muted)] text-sm">{{ $review->product?->name ?? 'Deleted product' }}</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 rounded-lg bg-[var(--color-danger-bg)] border border-[var(--color-danger-border)]">
                    <ul class="text-sm text-[var(--color-danger-accent)] space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('review.store', $review->token) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                {{-- Star Rating --}}
                <div>
                    <label class="block text-sm text-[var(--color-text)] mb-2" class="font-heading-wide">RATING <span class="text-[var(--color-danger-accent)]">*</span></label>
                    <div x-data="{ rating: 0, hover: 0 }" class="flex gap-1 mb-4">
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button" @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0"
                                :class="(hover || rating) >= {{ $i }} ? 'text-amber-400' : 'text-[var(--color-input-border)]'"
                                class="text-3xl transition-colors">&#9733;</button>
                        @endfor
                        <input type="hidden" name="rating" :value="rating">
                    </div>
                </div>

                {{-- Title --}}
                <div>
                    <label class="block text-sm text-[var(--color-text)] mb-2" class="font-heading-wide">TITLE (OPTIONAL)</label>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Summarize your experience"
                        class="review-form-input w-full">
                </div>

                {{-- Body --}}
                <div>
                    <label class="block text-sm text-[var(--color-text)] mb-2" class="font-heading-wide">YOUR REVIEW (OPTIONAL)</label>
                    <textarea name="body" rows="5" placeholder="Tell us more about your experience..."
                        class="review-form-input w-full">{{ old('body') }}</textarea>
                </div>

                {{-- Images --}}
                <div>
                    <label class="block text-sm text-[var(--color-text)] mb-2" class="font-heading-wide">PHOTOS (MAX 2)</label>
                    <input type="file" name="images[]" multiple accept="image/*"
                        class="block w-full text-sm text-[var(--color-text-muted)] file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:bg-[var(--color-search-bg)] file:text-[var(--color-text)] hover:file:bg-[var(--color-border)]">
                    <p class="mt-1 text-xs text-[var(--color-text-muted)]">Max 2MB per image.</p>
                </div>

                <button type="submit" class="product-add-btn w-full">SUBMIT REVIEW</button>
            </form>
        </div>
    </div>
</x-store-layout>
