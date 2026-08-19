<x-store-layout title="{{ $subscribed ? 'Resubscribed' : 'Unsubscribed' }}">
    <div class="mx-auto max-w-xl px-4 py-16 text-center">
        @if ($subscribed)
            <h1 class="font-heading text-2xl text-[var(--color-text-heading)]">You are back on the list</h1>
            <p class="mt-3 text-[var(--color-text-muted)]">{{ $customer->email }} will receive our emails again.</p>
        @else
            <h1 class="font-heading text-2xl text-[var(--color-text-heading)]">You are unsubscribed</h1>
            <p class="mt-3 text-[var(--color-text-muted)]">{{ $customer->email }} will no longer receive our emails.</p>
            <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('newsletter.resubscribe', ['customer' => $customer->id]) }}"
               class="mt-6 inline-block rounded bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-[var(--color-btn-text)]">
                Undo, keep me subscribed
            </a>
        @endif
    </div>
</x-store-layout>
