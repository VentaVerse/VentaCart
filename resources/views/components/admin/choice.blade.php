{{-- A pick-one set where each option is large enough to carry its
     own qualifiers. Wrap x-admin.choice.card children.

     `cols` lays the same options across instead of down, up to four
     wide, for a short set of equals that is read by comparison rather
     than scanned down a list. It drops to two columns on a narrow
     sheet and one on a phone, so a card never shrinks below reading
     width. --}}
@props(['cols' => false])
<div {{ $attributes->merge(['class' => 'v-choice' . ($cols ? ' v-choice--cols' : '')]) }}>{{ $slot }}</div>
