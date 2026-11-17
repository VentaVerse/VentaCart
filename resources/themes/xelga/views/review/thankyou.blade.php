{{-- After a review is sent: core holds it for the store to approve before it shows. --}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('page'); @endphp
<x-store-layout title="Thank you">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="review-thanks">
        <div class="s__inner">
            <div class="review-page review-page--done">
                <span class="done-head__icon" aria-hidden="true"><svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg></span>
                <h1 class="auth__title">Thank you for your review</h1>
                <p class="muted">It shows on the product page once the store has approved it.</p>
                <p><a class="btn" href="{{ route('home') }}">Continue shopping</a></p>
            </div>
        </div>
    </div>
</x-store-layout>
