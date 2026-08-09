{{--
    The last few things customers did: an order placed, something carted, a
    login. Deleted from an earlier pass as noise and restored on the owner's
    ruling, because "is anyone out there" is a question the morning read
    answers. A feed capped to a glance, newest first; the customer's own
    page carries their full history.
--}}
<section class="v-panel" aria-labelledby="v-sheet-activity">
    <div class="v-panel__head">
        <h2 class="v-subhead" id="v-sheet-activity">Customer activity</h2>
    </div>

    @if ($activities->isEmpty())
        <p class="v-panel__blank">Nothing recorded yet.</p>
    @else
        <ul class="v-feed">
            @foreach ($activities as $activity)
                <li class="v-feed__row">
                    <span class="v-feed__ic {{ $activity->color }}">
                        <x-admin.icon :name="$activity->icon" class="w-3.5 h-3.5" />
                    </span>
                    <span class="v-feed__body">
                        <span class="v-feed__what">
                            @if ($activity->customer)
                                <a href="{{ route('admin.customers.edit', $activity->customer) }}" class="v-feed__who">{{ $activity->customer->first_name }} {{ $activity->customer->last_name }}</a>
                            @else
                                <span class="v-feed__gone">A deleted customer</span>
                            @endif
                            {{ lcfirst($activity->description) }}
                        </span>
                        <span class="v-feed__when">{{ $activity->created_at->diffForHumans() }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
