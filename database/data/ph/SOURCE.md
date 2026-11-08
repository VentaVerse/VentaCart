# PH address data: sources

## PSGC files (provinces, cities, districts, barangays)

`region.json`, `province.json`, `muncity.json` and `barangay.json` are the raw
PSGC files bundled since the old philippine-address extension. They follow an
older PSGC release, with 9-digit codes and the old region numbering (15 = ARMM,
16 = Caraga, 17 = MIMAROPA, with no Negros Island Region). They are kept as the
source on purpose: saved addresses store the province, city, district and
barangay *names*, and the checkout cascade finds codes by matching those names.

`php artisan ph:build-address-data` builds `provinces.json`, `cities.json`,
`districts.json` and `barangays.json` from them.

## Regions (`config/localization.php`, `deep_address.PH.regions`)

The keys are the 2-digit region prefixes used by the codes above, with today's
official names. `region_overrides` moves Negros Occidental (0645), Negros
Oriental (0746) and Siquijor (0761) to the Negros Island Region (18), created by
RA 12000 (2024).

## ZIP codes (`city-zip-map.json`)

The map is keyed by the 9-digit PSGC code of a city/municipality or Manila
district, with the 4-digit ZIP as the value. Cities that aren't listed get
`zip_code: null`.

Source: PHLPost ZIP Code Locator, https://phlpost.gov.ph/zip-code-locator/
(the on-page table of Region, Province, City/Municipality and ZIP Code; it is
also the "Zip Code Finder" on the homepage). Fetched 2026-11-08. The page has no
downloadable directory.

That table has 960 rows and is incomplete. It has no Metro Manila rows (except
Pandacan), and nothing for Ilocos Norte/Sur, La Union, Western and Central
Visayas, most of Eastern Visayas, Zamboanga Peninsula or BARMM. The 1,492
entries therefore come from:

| Source | Entries |
| --- | --- |
| PHLPost table, matched by province and name | 886 |
| PHLPost table, matched through an alias (see below) | 28 |
| Earlier map, kept for regions 01–09 only | 548 |
| Metro Manila main post-office ZIPs, entered by hand | 30 |

**PHLPost aliases.** Spellings: Pinukpok/Pinukpuk, Batazara/Bataraza,
Palauan/Paluan, Polilio/Polillo, Wright/Paranas, Cortez/Cortes, Caluan/Calauan,
Talugtog/Talugtug, Carrangalan/Carranglan, Trece Martirez/Trece Martires,
Ozamis/Ozamiz, Naguillan/Naguilian, and abbreviated names (Gen., Gov., Pres.).
Six rows are filed under the wrong province on PHLPost's page. Their ZIP was
kept and placed in the right province: T'boli (South Cotabato), Kiamba
(Sarangani), Lantapan and Kitaotao (Bukidnon) were all listed under Agusan del
Norte; Tagoloan (Misamis Oriental) and Sapang Dalaga (Misamis Occidental) were
listed under Aurora. Carmen 8315, listed under Surigao del Norte, is Carmen,
Surigao del Sur. Post-office rows that aren't a city or municipality (Davao
City's district offices, Clark, PMA, Molino and similar) are skipped. Cajidiocan
is skipped because its ZIP cell holds the town name.

**Earlier map.** The old file was keyed by `muncity_id`, and those ids drift out
of line from region 10 onward. In regions 01–09 it agreed with PHLPost for 488
of 509 cities checked; PHLPost wins wherever they differ. Its values are kept
for the region 01–09 cities PHLPost doesn't list, except ones outside the
province's ZIP block (Malinao, Aklan; Bugasong, Antique; San Miguel, Bulacan;
four in Rizal, which PHLPost has). Nothing from it is kept for region 10 and
above.

**Metro Manila (hand-entered, not on PHLPost's table; verify against PHLPost's
NCR directory when one is published).** Cities: Manila 1000, Caloocan 1400,
Las Piñas 1740, Makati 1200, Malabon 1470, Mandaluyong 1550, Marikina 1800,
Muntinlupa 1770, Navotas 1485, Parañaque 1700, Pasay 1300, Pasig 1600,
Pateros 1620, Quezon City 1100, San Juan 1500, Taguig 1630, Valenzuela 1440.
Manila districts: Binondo 1006, Ermita 1000, Intramuros 1002, Malate 1004,
Paco 1007, Pandacan 1011 (PHLPost), Port Area 1018, Quiapo 1001,
Sampaloc 1008, San Miguel 1005, San Nicolas 1010, Santa Ana 1009,
Santa Cruz 1003, Tondo I / II 1012. Cities and districts with several ZIPs
carry their main office's ZIP.

**No ZIP (154 cities).** These are left empty rather than carry the earlier
map's shifted values: all of Basilan, Lanao del Sur, Maguindanao, Sulu,
Tawi-tawi and Mountain Province; 9 in Bukidnon; and single towns elsewhere
(Getafe, Pinamungahan, Dueñas, Sapi-An, Salvador Benedicto, Vallehermoso,
Villaverde, Cajidiocan, Limasawa, Tomas Oppus, Roseller Lim, Malinao (Aklan),
Bugasong, and 4 in Zamboanga del Norte). The customer types the postcode for
these.
