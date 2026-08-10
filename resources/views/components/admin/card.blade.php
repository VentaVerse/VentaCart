{{-- The card IS the panel now. Same sheet, same ruled head, same
     gutter as the day sheet and the fulfillment queue, so a settings
     screen and the dashboard stop reading as two products.

     The prop API is unchanged, because 79 views call this: `title`,
     `description`, the `headerActions` slot and the `footer` slot all
     behave exactly as before. What changed is what they render.

     `description` survives on exactly one caller, and the bar it has
     to clear is high: a consequence the operator cannot recover from
     (profile/delete-user-form). Forty-six others said what their own
     title already said, and were deleted; the handful that carried a
     real constraint moved into x-admin.hint, which keeps the sentence
     reachable without printing it on the page. A card that wants a
     line of prose under its heading almost always wants a better
     heading.

     The one thing this does NOT take from the panel is the panel's
     clipping: a card holds fields, and a field holds a hint whose
     bubble opens below it. See .v-panel--open. --}}
@props(['title' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'v-panel v-panel--open']) }}>
    @if ($title || isset($headerActions))
        <div class="v-panel__head">
            <div class="min-w-0">
                @if ($title)<h2 class="v-subhead">{{ $title }}</h2>@endif
                @if ($description)<p class="v-panel__note mt-1">{{ $description }}</p>@endif
            </div>
            @isset($headerActions)<div class="ml-auto flex items-center gap-2">{{ $headerActions }}</div>@endisset
        </div>
    @endif
    <div class="v-panel__body">{{ $slot }}</div>
    @isset($footer)<div class="v-panel__foot">{{ $footer }}</div>@endisset
</section>
