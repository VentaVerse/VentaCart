{{--
    Variants: one group of choices per variant group core offers for this
    product. Color groups draw as swatches or buttons, other groups as
    buttons or a dropdown. Choices no switched-on variant can fill are
    marked by the page script, from core's list of variants.
--}}
@if ($data['variant_types'] !== [])
    <div class="pinfo__variants" data-block-id="{{ $block['id'] }}" data-block-type="variants" @if ($block['disabled']) hidden @endif>
        @foreach ($data['variant_types'] as $type)
            @php
                $fieldId = $section['id'] . '-opt-' . $type['id'];
                $asSelect = ! $type['color'] && $settings['picker_size'] === 'dropdown';
            @endphp
            @if ($asSelect)
                <div class="opts opts--select" data-opts="{{ $type['id'] }}">
                    <label class="opts__legend" for="{{ $fieldId }}">{{ $type['name'] }}</label>
                    <select class="select" id="{{ $fieldId }}" name="variant-{{ $type['id'] }}" data-opt-select>
                        <option value="">Choose {{ mb_strtolower($type['name']) }}</option>
                        @foreach ($type['values'] as $value)
                            <option value="{{ $value['id'] }}">{{ $value['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <fieldset class="opts{{ $type['color'] ? ' opts--color' : '' }}" data-opts="{{ $type['id'] }}">
                    <legend class="opts__legend">{{ $type['name'] }}: <span data-opt-chosen>Choose one</span></legend>
                    <div class="opts__list">
                        @foreach ($type['values'] as $value)
                            <label class="opt{{ $type['color'] ? ' opt--swatch' : '' }}" title="{{ $value['label'] }}">
                                <input class="visually-hidden" type="radio" name="variant-{{ $section['id'] }}-{{ $type['id'] }}" value="{{ $value['id'] }}" data-label="{{ $value['label'] }}">
                                @if ($type['color'])
                                    <span class="swatch" data-swatch="{{ $value['key'] }}" aria-hidden="true"></span>
                                @endif
                                <span class="opt__text">{{ $value['label'] }}</span>
                                <span class="opt__state visually-hidden" data-opt-state></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        @endforeach
    </div>
@elseif ($data['unavailable'])
    <p class="stock-note" data-level="out" data-block-id="{{ $block['id'] }}" data-block-type="variants">This product is not available right now.</p>
@endif
