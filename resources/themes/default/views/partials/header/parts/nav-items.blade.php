{{-- The main menu, from the composed tree: every item knows its type, its
     resolved URL and what its dropdown holds. The header draws shape only. --}}
@foreach ($navTree as $node)
    @include('partials.header.parts.nav-node', ['node' => $node])
@endforeach
