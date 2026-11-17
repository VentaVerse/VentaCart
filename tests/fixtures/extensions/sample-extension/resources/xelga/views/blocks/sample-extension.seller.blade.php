{{-- A sample block inside the product information: who sells the product. --}}
<p data-sample-seller>{{ $settings['label'] }} Demo Store, for {{ $data['product'] }}@if ($data['link'] !== null) <a href="{{ $data['link'] }}">Visit</a>@endif</p>
