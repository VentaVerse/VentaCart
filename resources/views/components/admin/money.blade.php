{{-- An amount. The currency mark holds the left of its column and
     the digits are flushed right, so every amount on a page shares
     one decimal position and one mark position.

     Zero is a fact, not an alarm, so it recedes rather than shouting;
     pass `zero` when the figure genuinely is nothing, which is not
     always the same as the string being "0.00". --}}
@props(['mark', 'amount', 'zero' => false])
<span {{ $attributes->merge(['class' => 'v-money' . ($zero ? ' v-money--zero' : '')]) }}><span class="v-money__mark">{{ $mark }}</span><span class="v-money__amt">{{ $amount }}</span></span>
