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
<x-admin.panel title="Outstanding" id="v-sheet-standing" flush>
    <ul class="v-lines">
        @foreach ($standingLines as $line)
            <x-admin.line
                :href="$line['url']"
                :label="$line['label']"
                :count="$line['count']"
                :detail="$line['detail'] ?? null" />
        @endforeach
    </ul>
</x-admin.panel>
