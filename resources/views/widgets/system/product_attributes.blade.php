{{-- The product's specifications: its attribute groups, from the product
     page's bundle. Core's plain version, drawn when the theme has none. --}}
@php $attributeGroups = \App\Support\PageContext::get('attributeGroups', []); @endphp
@if (! empty($attributeGroups))
    <section class="product-attributes">
        @foreach ($attributeGroups as $group)
            <table class="product-attributes__table">
                <caption>{{ $group['name'] }}</caption>
                <tbody>
                    @foreach ($group['attributes'] as $row)
                        <tr><th scope="row">{{ $row['name'] }}</th><td>{{ $row['value'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </section>
@endif
