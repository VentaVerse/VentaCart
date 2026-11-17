{{-- Inside the live theme's account frame when it has one, else a plain page. --}}
@extends(view()->exists('extension.account') ? 'extension.account' : 'sample-extension::plain')
@section('title', 'Sample things')
@section('content')
    <p data-sample-account>Nothing here yet.</p>
@endsection
