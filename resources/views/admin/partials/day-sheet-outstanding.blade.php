{{--
    What is waiting and is not about today.

    Extracted because the sheet now has two compositions and both end on
    this panel: the trading store carries it in the rail beside the day's
    work, and the store that has never traded carries it under its opening
    counts. Two copies of the same list would have drifted the first time a
    line was added to one of them.

    Every line is a count with a destination, which is this page's whole
    vocabulary. It reads $unhealthyAutomations and $standingLines, both of
    which the controller shares with either composition.
--}}
<section class="v-panel" aria-labelledby="v-sheet-standing">
    <div class="v-panel__head">
        <h2 class="v-subhead" id="v-sheet-standing">Outstanding</h2>
    </div>
    <ul class="v-lines v-lines--rail">
        <li>
            <a href="{{ route('admin.automations.index') }}" class="v-line">
                <span class="v-line__label">
                    Automations overdue
                    @if ($unhealthyAutomations->isNotEmpty())
                        @php
                            $named = $unhealthyAutomations->pluck('name');
                            $rest = $named->count() - 3;
                            $detail = $named->take(3)->join(', ') . ($rest > 0 ? ', and ' . $rest . ' more' : '');
                        @endphp
                        <span class="v-line__detail">{{ $detail }}</span>
                    @endif
                </span>
                <span class="v-num v-line__count {{ $unhealthyAutomations->isEmpty() ? 'v-line__count--zero' : '' }}">{{ $unhealthyAutomations->count() }}</span>
                <x-admin.icon name="chevron-right" class="v-line__go" />
            </a>
        </li>
        @foreach ($standingLines as $line)
            <li>
                <a href="{{ $line['url'] }}" class="v-line">
                    <span class="v-line__label">{{ $line['label'] }}</span>
                    <span class="v-num v-line__count {{ $line['count'] === 0 ? 'v-line__count--zero' : '' }}">{{ number_format($line['count']) }}</span>
                    <x-admin.icon name="chevron-right" class="v-line__go" />
                </a>
            </li>
        @endforeach
    </ul>
</section>
