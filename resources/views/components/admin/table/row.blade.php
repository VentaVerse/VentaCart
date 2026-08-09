{{-- A row that stands for a record.

     `href` used to make the <tr> itself the control: tabindex="0" plus
     role="link". The role was the defect. It overrides the implicit
     `row` role, so the row leaves the table's grid structure for
     assistive technology, its cells lose their row context, and the
     row's accessible name becomes every cell's text concatenated.

     The row keeps `data-href`, which is what admin.js reads to open
     the record on a click anywhere that is not already interactive.
     That is a pointer convenience. The keyboard and the screen reader
     get to the record through the real link every one of these tables
     already prints in the row, which is a control with a name.
--}}
@props(['href' => null])
<tr {{ $attributes->merge(['class' => 'v-row ' . ($href ? 'relative' : '')]) }} @if($href) data-href="{{ $href }}" @endif>
    {{ $slot }}
</tr>
