@props(['name'])
<div x-show="tab === '{{ $name }}'" x-cloak role="tabpanel"
     id="v-tabpanel-{{ $name }}" aria-labelledby="v-tab-{{ $name }}"
     {{ $attributes }}>{{ $slot }}</div>
