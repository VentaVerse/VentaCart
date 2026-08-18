{{-- One line of a feed: a mark naming the kind of act, one line of
     fact, and when. Ported from the v2 layer; `tone` is now a
     semantic key (act, info, warn, quiet) rather than utility
     strings. --}}
@props(['icon', 'when', 'tone' => 'quiet', 'who' => null, 'whoHref' => null])
<li>
    <div {{ $attributes->merge(['class' => 'v3-dayfeed__row']) }}>
        <span class="v3-dayfeed__ic v3-dayfeed__ic--{{ $tone }}">
            <x-admin3.icon :name="$icon" />
        </span>
        <span class="v3-dayfeed__body">
            <span class="v3-dayfeed__what">
                @if ($who && $whoHref)
                    <a href="{{ $whoHref }}" class="v3-dayfeed__who v3-quietlink">{{ $who }}</a>
                @elseif ($who)
                    <span class="v3-dayfeed__gone">{{ $who }}</span>
                @endif
                {{ $slot }}
            </span>
            @isset($note)<span class="v3-dayfeed__note">{{ $note }}</span>@endisset
            <span class="v3-dayfeed__when">{{ $when }}</span>
        </span>
        @isset($action)<span class="v3-dayfeed__act">{{ $action }}</span>@endisset
    </div>
</li>
