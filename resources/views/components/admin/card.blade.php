{{-- `description` survives on exactly one caller, and the bar it has to
     clear is high: a consequence the operator cannot recover from
     (profile/delete-user-form). Forty-six others said what their own
     title already said, and were deleted; the handful that carried a
     real constraint moved into x-admin.hint, which keeps the sentence
     reachable without printing it on the page. A card that wants a
     line of prose under its heading almost always wants a better
     heading. --}}
@props(['title' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'bg-v-surface border border-v-border rounded-v-lg']) }}>
    @if ($title || isset($headerActions))
        <header class="flex items-start justify-between gap-3 px-6 pt-5 pb-0">
            <div>
                @if ($title)<h2 class="text-[15px] font-semibold text-v-text">{{ $title }}</h2>@endif
                @if ($description)<p class="mt-0.5 text-[13px] text-v-secondary">{{ $description }}</p>@endif
            </div>
            @isset($headerActions)<div class="flex items-center gap-2">{{ $headerActions }}</div>@endisset
        </header>
    @endif
    <div class="px-6 py-5">{{ $slot }}</div>
    @isset($footer)
        <footer class="px-6 py-3 border-t border-v-border bg-v-sunken rounded-b-v-lg">{{ $footer }}</footer>
    @endisset
</section>
