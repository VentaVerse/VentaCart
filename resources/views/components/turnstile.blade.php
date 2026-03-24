@php
    $turnstile = app(\App\Services\TurnstileService::class);
@endphp

@if ($turnstile->isEnabled())
    @once
        @push('head')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce

    <div class="cf-turnstile" data-sitekey="{{ $turnstile->getSiteKey() }}" data-theme="auto" {!! !empty($id) ? 'id="' . e($id) . '"' : '' !!}></div>

    @error('cf-turnstile-response')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
@endif
