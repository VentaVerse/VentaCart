<x-store-layout title="Thank You">
    <div class="container-store py-12">
        <div class="max-w-md mx-auto text-center">
            <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-[var(--color-success-bg)] flex items-center justify-center">
                <svg class="w-8 h-8 text-[var(--color-success-accent)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h1 class="text-2xl mb-4" class="font-heading-wide">THANK YOU!</h1>
            <p class="text-[var(--color-text-muted)]">Your review has been submitted and is pending approval.</p>
            <a href="{{ route('home') }}" class="inline-block mt-8 px-6 py-2 text-sm rounded transition-colors"
                style="font-family: var(--font-heading); letter-spacing: 0.1em; background: var(--color-primary); color: #FFFFFF;">
                CONTINUE SHOPPING
            </a>
        </div>
    </div>
</x-store-layout>
