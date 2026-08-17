                    @if ($approvedReviews->isNotEmpty())
                        <div class="space-y-6">
                            @foreach ($approvedReviews as $idx => $review)
                                <div class="review-card" x-show="{{ $idx }} >= (reviewPage - 1) * perPage && {{ $idx }} < reviewPage * perPage">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-sm font-medium text-[var(--color-text-heading)]">{{ $review->name }}</span>
                                            </div>
                                            <div class="star-rating">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= $review->rating)
                                                        <span>&#9733;</span>
                                                    @else
                                                        <span class="star-empty">&#9733;</span>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>
                                        <span class="text-xs text-[var(--color-text-muted)]">{{ $review->published_at?->format('M j, Y') }}</span>
                                    </div>
                                    @if ($review->title)
                                        <h4 class="text-sm font-semibold text-[var(--color-text-heading)] mb-1">{{ $review->title }}</h4>
                                    @endif
                                    @if ($review->body)
                                        <p class="text-sm text-[var(--color-text)] whitespace-pre-wrap">{{ $review->body }}</p>
                                    @endif
                                    @if ($review->images->isNotEmpty())
                                        <div class="flex gap-2 mt-3">
                                            @foreach ($review->images as $img)
                                                <a href="{{ storage_url($img->path) }}" target="_blank" class="w-16 h-16 rounded overflow-hidden border border-[var(--color-border)]">
                                                    <img src="{{ storage_url($img->path) }}" alt="Review photo" class="w-full h-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($approvedReviews->count() > 20)
                            <div class="flex items-center justify-center gap-2 mt-6 pt-4 border-t border-[var(--color-border)]">
                                <button @click="reviewPage = Math.max(1, reviewPage - 1); document.getElementById('reviews').scrollIntoView({behavior: 'smooth'})"
                                    :disabled="reviewPage === 1"
                                    style="padding: 6px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #d1d5db; color: #6b7280; background: transparent; cursor: pointer;"
                                    :style="reviewPage === 1 && 'opacity: 0.3; cursor: not-allowed;'">
                                    &laquo; Prev
                                </button>
                                <span style="font-size: 13px; color: #9ca3af;">
                                    Page <span x-text="reviewPage"></span> of <span x-text="Math.ceil({{ $approvedReviews->count() }} / perPage)"></span>
                                </span>
                                <button @click="reviewPage = Math.min(Math.ceil({{ $approvedReviews->count() }} / perPage), reviewPage + 1); document.getElementById('reviews').scrollIntoView({behavior: 'smooth'})"
                                    :disabled="reviewPage >= Math.ceil({{ $approvedReviews->count() }} / perPage)"
                                    style="padding: 6px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #d1d5db; color: #6b7280; background: transparent; cursor: pointer;"
                                    :style="reviewPage >= Math.ceil({{ $approvedReviews->count() }} / perPage) && 'opacity: 0.3; cursor: not-allowed;'">
                                    Next &raquo;
                                </button>
                            </div>
                        @endif
                    @else
                        <p class="text-[var(--color-text-muted)] text-sm">No reviews yet.</p>
                    @endif
