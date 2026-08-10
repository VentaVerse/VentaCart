{{-- One line of a feed: a mark naming the kind of act, one line of
     fact, and when.

     The actor's name is the feed's link into the record, but a column
     of brass names would say "act here" eight times down one rail, so
     it takes the row's own colour and says it on demand instead. Pass
     it as `who` with `whoHref`, or leave `who` for a record that is
     gone.

     Two optional slots carry the shapes a history has and an activity
     rail does not. `note` is the line the act left behind: an
     operator's comment on a status change, the reason a job failed.
     `action` is the one control the row allows, which for a record of
     something that happened is almost always "remove this record". --}}
@props(['icon', 'when', 'tone' => null, 'who' => null, 'whoHref' => null])
<li>
    <div {{ $attributes->merge(['class' => 'v-feed__row']) }}>
        <span class="v-feed__ic {{ $tone }}">
            <x-admin.icon :name="$icon" class="w-3.5 h-3.5" />
        </span>
        <span class="v-feed__body">
            <span class="v-feed__what">
                @if ($who && $whoHref)
                    <a href="{{ $whoHref }}" class="v-feed__who v-quietlink">{{ $who }}</a>
                @elseif ($who)
                    {{-- A record that is gone is not a link and does not take
                         the actor's weight: it is the sentence's subject
                         having been deleted, which is a fact about the row
                         rather than a name in it. --}}
                    <span class="v-feed__gone">{{ $who }}</span>
                @endif
                {{ $slot }}
            </span>
            @isset($note)<span class="v-feed__note">{{ $note }}</span>@endisset
            <span class="v-feed__when">{{ $when }}</span>
        </span>
        @isset($action)<span class="v-feed__act">{{ $action }}</span>@endisset
    </div>
</li>
