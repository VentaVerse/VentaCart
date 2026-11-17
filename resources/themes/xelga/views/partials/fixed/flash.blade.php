{{-- The messages core flashes after a form: success, error, status, and validation errors. --}}
@foreach (['success' => 'ok', 'status' => 'ok', 'error' => 'error'] as $flashKey => $flashTone)
    @if (session($flashKey) && is_string(session($flashKey)))
        <div class="form-status form-status--{{ $flashTone }}" role="{{ $flashTone === 'ok' ? 'status' : 'alert' }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-{{ $flashTone === 'ok' ? 'check' : 'alert' }}"></use></svg><span>{{ session($flashKey) }}</span></div>
    @endif
@endforeach
@if (! empty($showErrors) && $errors->any())
    <div class="form-status form-status--error" role="alert">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg>
        @if (count($errors->all()) === 1)
            <span>{{ $errors->first() }}</span>
        @else
            <ul class="form-status__list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
