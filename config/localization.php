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
    |   required    whether the field is required. A conditional level is
    |               required only where it has options (district: Manila).
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
                    'required'    => true,
                    'file'        => 'districts.json',
                    'parent'      => 'city',
                    'conditional' => true,
                ],
                [
                    'key'      => 'barangay',
                    'label'    => 'Barangay',
                    'required' => true,
                    'file'     => 'barangays.json',
                    'parent'   => 'city|district',
                ],
            ],

            // Regions, keyed by the 2-digit region prefix of the PSGC codes in
            // the bundled files (that release's numbering: 15 is the former
            // ARMM, 16 Caraga, 17 MIMAROPA). Names are today's.
            // Look up with DeepAddressData::region('PH', $provinceCode).
            'regions' => [
                '01' => ['designation' => 'Region I',    'name' => 'Ilocos Region',                                   'short' => 'Ilocos'],
                '02' => ['designation' => 'Region II',   'name' => 'Cagayan Valley',                                  'short' => 'Cagayan Valley'],
                '03' => ['designation' => 'Region III',  'name' => 'Central Luzon',                                   'short' => 'Central Luzon'],
                '04' => ['designation' => 'Region IV-A', 'name' => 'CALABARZON',                                      'short' => 'Calabarzon'],
                '05' => ['designation' => 'Region V',    'name' => 'Bicol Region',                                    'short' => 'Bicol'],
                '06' => ['designation' => 'Region VI',   'name' => 'Western Visayas',                                 'short' => 'Western Visayas'],
                '07' => ['designation' => 'Region VII',  'name' => 'Central Visayas',                                 'short' => 'Central Visayas'],
                '08' => ['designation' => 'Region VIII', 'name' => 'Eastern Visayas',                                 'short' => 'Eastern Visayas'],
                '09' => ['designation' => 'Region IX',   'name' => 'Zamboanga Peninsula',                             'short' => 'Zamboanga Peninsula'],
                '10' => ['designation' => 'Region X',    'name' => 'Northern Mindanao',                               'short' => 'Northern Mindanao'],
                '11' => ['designation' => 'Region XI',   'name' => 'Davao Region',                                    'short' => 'Davao'],
                '12' => ['designation' => 'Region XII',  'name' => 'SOCCSKSARGEN',                                    'short' => 'Soccsksargen'],
                '13' => ['designation' => 'NCR',         'name' => 'National Capital Region',                         'short' => 'Metro Manila'],
                '14' => ['designation' => 'CAR',         'name' => 'Cordillera Administrative Region',                'short' => 'Cordillera'],
                '15' => ['designation' => 'BARMM',       'name' => 'Bangsamoro Autonomous Region in Muslim Mindanao', 'short' => 'Bangsamoro'],
                '16' => ['designation' => 'Region XIII', 'name' => 'Caraga',                                          'short' => 'Caraga'],
                '17' => ['designation' => 'Region IV-B', 'name' => 'MIMAROPA Region',                                 'short' => 'Mimaropa'],
                '18' => ['designation' => 'NIR',         'name' => 'Negros Island Region',                            'short' => 'Negros Island'],
            ],

            // Provinces that moved region after the bundled PSGC release, keyed
            // by their 4-digit code prefix: Negros Occidental, Negros Oriental
            // and Siquijor form the Negros Island Region (RA 12000, 2024).
            'region_overrides' => [
                '0645' => '18',
                '0746' => '18',
                '0761' => '18',
            ],
        ],

    ],

];
