{{--
    The last few things customers did: an order placed, something carted, a
    login. Deleted from an earlier pass as noise and restored on the owner's
    ruling, because "is anyone out there" is a question the morning read
    answers. A feed capped to a glance, newest first; the customer's own
    page carries their full history.
--}}
<x-admin3.panel title="Customer activity" id="v-sheet-activity" flush>
    @if ($activities->isEmpty())
        <p class="v3-blankline">Nothing recorded yet.</p>
    @else
        <ul class="v3-dayfeed">
            @foreach ($activities as $activity)
                <x-admin3.dayfeed-entry
                    :icon="$activity->icon"
                    :tone="$activity->color"
                    :when="$activity->created_at->diffForHumans()"
                    :who="$activity->customer ? $activity->customer->first_name . ' ' . $activity->customer->last_name : 'A deleted customer'"
                    :who-href="$activity->customer ? route('admin.customers.edit', $activity->customer) : null">
                    {{ lcfirst($activity->description) }}
                </x-admin3.dayfeed-entry>
            @endforeach
        </ul>
    @endif
</x-admin3.panel>
