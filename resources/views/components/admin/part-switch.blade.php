@props(['text' => null])
{{-- One page part, on or off, as the admin's switch: a part is either shown
     or not, and a checkbox would read as "pick the parts you want". The
     visible text is the part's own label unless the caller gives one (the
     footer bar says "Show on this page" beside its heading); the switch's
     accessible name always names the part. --}}
<label class="v-toggle v-lay__partsw">
    <input type="checkbox" :checked="f.value"
           :aria-label="'Show ' + f.label.toLowerCase() + ' on this page'"
           @change="setPart(f, $event.target.checked)">
    <span class="v-toggle__track" aria-hidden="true"></span>
    @if ($text)
        <span>{{ $text }}</span>
    @else
        <span x-text="f.label"></span>
    @endif
</label>
