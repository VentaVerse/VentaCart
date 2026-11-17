{{-- Specifications: the product's attribute groups from VentaCart. --}}
@if ($data['groups'] !== [])
    <div class="spec-groups">
        @foreach ($data['groups'] as $group)
            <div class="spec-group">
                <h3 class="spec-group__title">{{ $group['name'] }}</h3>
                <dl class="spec-list">
                    @foreach ($group['attributes'] as $row)
                        <dt>{{ $row['name'] }}</dt><dd>{{ $row['value'] }}</dd>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </div>
@else
    @include('xelga::snippets.editor-note', ['note' => 'This product has no attributes in VentaCart, so shoppers do not see this panel.'])
@endif
