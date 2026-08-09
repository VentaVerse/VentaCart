{{--
    What is waiting and is not about today.

    Extracted because the sheet has two compositions and both end on this
    panel: the trading store carries it in the rail beside the day's work,
    and the store that has never traded carries it under its opening counts.
    Two copies of the same list would have drifted the first time a line was
    added to one of them.

    Every line is a count with a destination, which is this page's whole
    vocabulary, and a count carries one line of specificity when a single
    cheap read can name the newest thing behind it. It reads $standingLines,
    which the controller shares with either composition; automation health
    has its own panel now, with the per-row detail a bare count could not
    hold.
--}}
<section class="v-panel" aria-labelledby="v-sheet-standing">
    <div class="v-panel__head">
        <h2 class="v-subhead" id="v-sheet-standing">Outstanding</h2>
    </div>
    <ul class="v-lines v-lines--rail">
        @foreach ($standingLines as $line)
            <li>
                <a href="{{ $line['url'] }}" class="v-line">
                    <span class="v-line__label">
                        {{ $line['label'] }}
                        @if (! empty($line['detail']))
                            <span class="v-line__detail">{{ $line['detail'] }}</span>
                        @endif
                    </span>
                    <span class="v-num v-line__count {{ $line['count'] === 0 ? 'v-line__count--zero' : '' }}">{{ number_format($line['count']) }}</span>
                    <x-admin.icon name="chevron-right" class="v-line__go" />
                </a>
            </li>
        @endforeach
    </ul>
</section>
