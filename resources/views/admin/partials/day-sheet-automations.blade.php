{{--
    Automation health, by name.

    The sheet used to fold this into one Outstanding line, and the owner
    ruled the old dashboard's card more informative: which automation, how
    long since it ran, failed or merely late. So the ones that need a look
    are rows, worst first (failed, then never run, then stalest), and the
    ones that are fine are one quiet line rather than a list: a merchant
    reads this panel for exceptions, not for a roster.

    It reads $automationHealth, shared with both compositions; the store
    that has never traded draws it only when something is actually wrong.
--}}
<x-admin.panel title="Automations" id="v-sheet-autos" flush>
    <x-slot:acts><a href="{{ route('admin.automations.index') }}" class="v-link">Manage</a></x-slot:acts>
    @if ($automationHealth['total'] === 0)
        <p class="v-blankline">None configured.</p>
    @else
        @if ($automationHealth['unhealthy']->isNotEmpty())
            <ul class="v-autos">
                @foreach ($automationHealth['unhealthy'] as $automation)
                    <li class="v-auto">
                        <span class="v-auto__body">
                            <span class="v-auto__name">{{ $automation->name }}</span>
                            <span class="v-auto__sub">{{ $automation->last_run_at ? 'Last ran ' . $automation->last_run_at->diffForHumans() : 'Never run' }}</span>
                        </span>
                        <span class="v-badge v-badge--{{ $automation->last_run_status === 'failed' ? 'bad' : 'warn' }}">
                            {{ $automation->last_run_status === 'failed' ? 'Failed' : 'Overdue' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($automationHealth['unhealthy']->isEmpty())
            <p class="v-blankline">
                {{ $automationHealth['total'] === 1 ? 'Running on schedule.' : 'All ' . number_format($automationHealth['total']) . ' running on schedule.' }}
            </p>
        @elseif ($automationHealth['healthy'] > 0)
            <p class="v-autos__rest">
                The other {{ number_format($automationHealth['healthy']) }} {{ $automationHealth['healthy'] === 1 ? 'is' : 'are' }} on schedule.
            </p>
        @endif
    @endif
</x-admin.panel>
