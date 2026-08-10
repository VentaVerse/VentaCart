{{--
    Renders one CourierFulfillmentInterface::settingsSchema() field. Included
    (not an anonymous component) since it's only ever used from settings.blade.php
    and just needs plain passed variables:
      $field        the schema field array (key/label/type/options/encrypted/help)
      $value        current stored value for non-encrypted types
      $masked       masked display value for encrypted types, or null
      $isModeField  true for the field driving this card's Alpine `mode` state
--}}
@php
    $name = 'settings[' . $field['key'] . ']';
    $help = $field['help'] ?? null;
@endphp

@if ($field['type'] === 'toggle')
    <x-admin.field :name="$name" :help="$help">
        <x-admin.toggle :name="$name" :checked="($value ?? '0') === '1'" :label="$field['label']" />
    </x-admin.field>
@elseif ($field['type'] === 'select')
    {{-- Blade component tags can't embed a bare @if/@endif inside their own
         attribute list (breaks the tag's attribute parser), so the mode-toggle
         binding branches around the whole tag instead of the attribute. --}}
    <x-admin.field :name="$name" :label="$field['label']" :help="$help">
        @if ($isModeField)
            <x-admin.select :name="$name" class="max-w-xs" x-model="mode">
                @foreach ($field['options'] ?? [] as $opt)
                    <option value="{{ $opt['value'] }}" @selected((string) $value === (string) $opt['value'])>{{ $opt['label'] }}</option>
                @endforeach
            </x-admin.select>
        @else
            <x-admin.select :name="$name" class="max-w-xs">
                @foreach ($field['options'] ?? [] as $opt)
                    <option value="{{ $opt['value'] }}" @selected((string) $value === (string) $opt['value'])>{{ $opt['label'] }}</option>
                @endforeach
            </x-admin.select>
        @endif
    </x-admin.field>
@elseif ($field['type'] === 'password')
    <x-admin.field :name="$name" :label="$field['label']"
        :help="!empty($masked) ? 'Current value ends in ' . $masked . '. Leave blank to keep it.' : $help">
        <x-admin.input :name="$name" type="password" autocomplete="off" placeholder="Enter to change" />
    </x-admin.field>
@else
    <x-admin.field :name="$name" :label="$field['label']" :help="$help">
        <x-admin.input :name="$name" :value="$value" />
    </x-admin.field>
@endif
