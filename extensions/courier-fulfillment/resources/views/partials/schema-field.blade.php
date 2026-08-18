{{--
    Renders one CourierFulfillmentInterface::settingsSchema() field in the
    v vocabulary. Included (not an anonymous component) since it's only
    used from settings-courier.blade.php and needs plain passed variables:
      $field        the schema field array (key/label/type/options/encrypted/help)
      $value        current stored value for non-encrypted types
      $masked       masked display value for encrypted types, or null
      $plain        decrypted value for type='secret' only, or null (unused
                    in v: secrets are never server-rendered into the page)
      $isModeField  true for the field driving the page's Alpine `mode` state

    `help` and `hint` both fold into the v field's "?" mark (owner law:
    no instructional prose on the page).
--}}
@php
    $name = 'settings[' . $field['key'] . ']';
    $helpText = trim(implode(' ', array_filter([$field['help'] ?? null, $field['hint'] ?? null])));
    $helpText = $helpText !== '' ? $helpText : null;
@endphp

@if ($field['type'] === 'toggle')
    <div class="v-gap-sm">
        <label class="v-toggle">
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox" name="{{ $name }}" value="1" @checked(($value ?? '0') === '1')>
            <span class="v-toggle__track" aria-hidden="true"></span>
            {{ $field['label'] }}
            @if ($helpText)<x-admin.help :text="$helpText" />@endif
        </label>
    </div>
@elseif ($field['type'] === 'select')
    <div class="v-gap-sm">
        <x-admin.field :name="$name" :label="$field['label']" :hint="$helpText">
            @if ($isModeField)
                <select name="{{ $name }}" class="v-control v-w-name" x-model="mode">
                    @foreach ($field['options'] ?? [] as $opt)
                        <option value="{{ $opt['value'] }}" @selected((string) $value === (string) $opt['value'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            @else
                <select name="{{ $name }}" class="v-control v-w-name">
                    @foreach ($field['options'] ?? [] as $opt)
                        <option value="{{ $opt['value'] }}" @selected((string) $value === (string) $opt['value'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            @endif
        </x-admin.field>
    </div>
@elseif (in_array($field['type'], ['secret', 'password'], true))
    <div class="v-gap-sm">
        <x-admin.field :name="$name" :label="$field['label']"
            :hint="trim((!empty($masked) ? 'Current value ends in ' . substr(ltrim($masked, '•'), -4) . '. Blank keeps it. ' : '') . ($helpText ?? '')) ?: null">
            <input type="password" name="{{ $name }}" autocomplete="off" class="v-control v-mono"
                   placeholder="{{ !empty($masked) ? 'Leave blank to keep current' : ($field['placeholder'] ?? '') }}">
        </x-admin.field>
    </div>
@else
    <div class="v-gap-sm">
        <x-admin.field :name="$name" :label="$field['label']" :hint="$helpText">
            <input type="text" name="{{ $name }}" value="{{ $value }}" class="v-control"
                   placeholder="{{ $field['placeholder'] ?? '' }}">
        </x-admin.field>
    </div>
@endif
