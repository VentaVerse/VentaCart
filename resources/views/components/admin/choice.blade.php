{{-- A pick-one list where each option is large enough to carry its
     own qualifiers. Wrap x-admin.choice.card children. --}}
<div {{ $attributes->merge(['class' => 'v-choice']) }}>{{ $slot }}</div>
