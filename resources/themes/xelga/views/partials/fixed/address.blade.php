{{-- One address as core stores it, as lines. --}}
<address class="addr">
    {{ trim($a['first_name'] . ' ' . $a['last_name']) }}<br>
    {{ $a['address_1'] }}<br>
    @if (! empty($a['barangay'])){{ $a['barangay'] }}<br>@endif
    @if (! empty($a['district'])){{ $a['district'] }}<br>@endif
    {{ $a['city'] }}@if (! empty($a['state'])), {{ $a['state'] }}@endif @if (! empty($a['postcode'])) {{ $a['postcode'] }}@endif<br>
    @if (! empty($a['country'])){{ $a['country'] }}<br>@endif
    @if (! empty($a['phone']))<span class="tabular">{{ $a['phone'] }}</span>@endif
</address>
