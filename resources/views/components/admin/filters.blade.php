{{-- The filter row: one control per job, on one line, with the way
     back beside them. A GET form, so every filtered view is a URL a
     person can bookmark or send to someone else. --}}
@props(['action' => null, 'method' => 'GET'])
<form method="{{ $method }}" @if ($action) action="{{ $action }}" @endif {{ $attributes->merge(['class' => 'v-filters']) }}>{{ $slot }}</form>
