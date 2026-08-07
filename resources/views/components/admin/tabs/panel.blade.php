@props(['name'])
<div x-show="tab === '{{ $name }}'" x-cloak role="tabpanel" {{ $attributes }}>{{ $slot }}</div>
