<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deep address localization
    |--------------------------------------------------------------------------
    |
    | Every country uses the standard Country -> State/Province (DB `zones`).
    | Countries listed here gain *additional* sub-levels below the province.
    | That deep data is read on-demand from bundled JSON files in
    | database/data/<path>/ — it is NEVER seeded into the database, so the
    | schema stays 100% generic (no country-specific tables).
    |
    | Keyed by ISO 3166-1 alpha-2. The Philippines is the only entry today;
    | the structure is generic so any country can be added later.
    |
    | The deep data is served from CLEANED files (built by `php artisan
    | ph:build-address-data` from the raw PSGC source). Each cleaned row carries
    | its own PSGC `code` and its parent's `code`, so linkage is an exact
    | parent-code match — no fragile prefix logic. The build replays the legacy
    | remapping (synthetic "Metro Manila", HUCs as cities, NCR renames, Manila
    | districts) so the cascade reproduces exactly what created live addresses.
    |
    | Each level:
    |   key         frontend field key (also the address column it saves into)
    |   label       dropdown label
    |   required    whether the field is required
    |   file        cleaned JSON file under database/data/<path>/
    |   parent      key of the parent level (null = root). May be "a|b" so a
    |               level can hang off either of two parents (PH barangay hangs
    |               off district when present, else city).
    |   conditional true = hide the dropdown when it returns no options (e.g. the
    |               district level, which only exists for Manila).
    |
    */

    'deep_address' => [

        'PH' => [
            'country_name' => 'Philippines',
            'phone_code'   => '63',
            'path'         => 'ph',
            'levels'       => [
                [
                    'key'      => 'state',
                    'label'    => 'Province',
                    'required' => true,
                    'file'     => 'provinces.json',
                    'parent'   => null,
                ],
                [
                    'key'      => 'city',
                    'label'    => 'City / Municipality',
                    'required' => true,
                    'file'     => 'cities.json',
                    'parent'   => 'state',
                ],
                [
                    'key'         => 'district',
                    'label'       => 'District',
                    'required'    => false,
                    'file'        => 'districts.json',
                    'parent'      => 'city',
                    'conditional' => true,
                ],
                [
                    'key'      => 'barangay',
                    'label'    => 'Barangay',
                    'required' => false,
                    'file'     => 'barangays.json',
                    'parent'   => 'city|district',
                ],
            ],
        ],

    ],

];
