{{-- A status badge for an extension's page: `name`, `tone` (App\Support\StatusTone: info, success, warning, danger, neutral), `large` (optional). --}}
@include('xelga::partials.fixed.status', ['name' => (string) ($name ?? ''), 'tone' => $tone ?? 'neutral', 'large' => ! empty($large)])
