{{--
    Renders one CourierFulfillmentInterface::settingsSchema() field. Included
    (not an anonymous component) since it's only ever used from settings.blade.php
    and just needs plain passed variables:
      $field        the schema field array (key/label/type/options/encrypted/help)
      $value        current stored value for non-encrypted types
      $masked       masked display value for encrypted types, or null
      $plain        decrypted value for type='secret' only, or null
      $isModeField  true for the field driving this card's Alpine `mode` state

    Two additive extensions, both opt-in from a courier's own schema, so no
    field QuadX or SPX declares renders any differently than before:

      type='secret'  the one-field stored key the security settings page
                     already uses (x-admin.secret): masked and read only at
                     rest, revealed by View, editable by Edit, and posting
                     nothing at all unless edited. type='password' keeps its
                     old always-empty box.
      placeholder    greyed text in an empty box, for a field with a real
                     default the operator should be able to see.
--}}
@php
    $name = 'settings[' . $field['key'] . ']';
    $help = $field['help'] ?? null;
    $plain = $plain ?? null;
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
@elseif ($field['type'] === 'secret')
    <x-admin.field :name="$name" :label="$field['label']" :help="$help">
        <x-admin.secret :name="$name" :masked="$masked" :value="$plain"
            :placeholder="$field['placeholder'] ?? null" />
    </x-admin.field>
@elseif ($field['type'] === 'password')
    <x-admin.field :name="$name" :label="$field['label']"
        :help="!empty($masked) ? 'Current value ends in ' . $masked . '. Leave blank to keep it.' : $help">
        <x-admin.input :name="$name" type="password" autocomplete="off" placeholder="Enter to change" />
    </x-admin.field>
@else
    <x-admin.field :name="$name" :label="$field['label']" :help="$help">
        <x-admin.input :name="$name" :value="$value" :placeholder="$field['placeholder'] ?? null" />
    </x-admin.field>
@endif
