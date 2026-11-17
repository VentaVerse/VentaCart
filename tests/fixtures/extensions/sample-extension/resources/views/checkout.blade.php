{{-- Inside the live theme's page frame when it has one, else a plain page. --}}
@extends(view()->exists('extension.page') ? 'extension.page' : 'sample-extension::plain')
@section('title', 'Checkout')
@section('content')
    <form method="POST" action="{{ route('checkout.place') }}" data-sample-checkout>
        @csrf
        @foreach ($groups as $group)
            <section data-group="{{ $group['key'] }}">
                @if ($group['label'] !== '')<h2>{{ $group['label'] }}</h2>@endif
                @foreach ($group['lines'] as $id)
                    <p>{{ $items[$id]->name }}</p>
                @endforeach
            </section>
        @endforeach
        <button type="submit">Place order</button>
    </form>
@endsection
