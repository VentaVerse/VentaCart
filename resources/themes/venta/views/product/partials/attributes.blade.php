{{-- The product's attributes, under their groups ($attributeGroups from the product page). --}}
@if (! empty($attributeGroups))
    <div class="product-attributes">
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
    </div>
@endif
