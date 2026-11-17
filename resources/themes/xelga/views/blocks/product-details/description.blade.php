{{-- The product's description, as core stores and prints it (rich text cleaned when it was saved). --}}
@if ($data['product'] && ! $panel['empty'])
    <div class="rte">{!! $data['product']->description !!}</div>
@else
    @include('xelga::snippets.editor-note', ['note' => 'This product has no description in VentaCart, so shoppers do not see this panel.'])
@endif
