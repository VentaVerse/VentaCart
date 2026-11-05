{{--
    Renders one CourierFulfillmentInterface::settingsSchema() field in the
    v vocabulary. Included (not an anonymous component) since it's only
    used from settings-courier.blade.php and needs plain passed variables:
      $field        the schema field array (key/label/type/options/encrypted/help)
      $value        current stored value for non-encrypted types
      $masked       masked display value for encrypted fields, or null
      $unreadable   true when an encrypted field's stored value no longer
                    decrypts
      $courierKey   the courier's key, for the reveal endpoint's URL
      $isModeField  true for the field driving the page's Alpine `mode` state

    Every encrypted field, whatever its declared type, renders as the
    shared stored-secret control (x-admin.secret). The page never carries
    a saved secret, only its mask; View asks the reveal endpoint for it.

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
@elseif (!empty($field['encrypted']) || in_array($field['type'], ['secret', 'password'], true))
    @php $errorKey = 'settings.' . $field['key']; @endphp
    {{-- Not x-admin.field: that wraps its content in a <label>, and a
         label around buttons sends a click on its text to the first one. --}}
    <div class="v-gap-sm">
        <div class="v-field" role="group" aria-labelledby="cf-secret-{{ $field['key'] }}">
            <span class="v-field__label" id="cf-secret-{{ $field['key'] }}">
                {{ $field['label'] }}
                @if ($helpText)<x-admin.help :text="$helpText" />@endif
            </span>
            <x-admin.secret :name="$name" type="text"
                :masked="$masked"
                :unreadable="!empty($unreadable)"
                :label="$field['label']"
                :maxlength="\Extensions\CourierFulfillment\Support\CourierSecrets::MAX_LENGTH"
                :editing="$errors->has($errorKey)"
                :reveal-url="route('admin.fulfillment.settings.reveal-secret', ['courier' => $courierKey, 'key' => $field['key']])"
                remove-name="remove_secrets[]"
                :remove-value="$field['key']"
                :placeholder="$field['placeholder'] ?? 'Paste the key'" />
            @error($errorKey)
                <span class="v-field__error">{{ $message }}</span>
            @enderror
        </div>
    </div>
@else
    <div class="v-gap-sm">
        <x-admin.field :name="$name" :label="$field['label']" :hint="$helpText">
            <input type="text" name="{{ $name }}" value="{{ $value }}" class="v-control"
                   placeholder="{{ $field['placeholder'] ?? '' }}">
        </x-admin.field>
    </div>
@endif
