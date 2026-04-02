<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        // Build country_code => country_id lookup
        $countryIds = Country::pluck('id', 'iso2')->toArray();

        $zones = [
            // United States
            ['country_code' => 'US', 'name' => 'Alabama', 'code' => 'AL'],
            ['country_code' => 'US', 'name' => 'Alaska', 'code' => 'AK'],
            ['country_code' => 'US', 'name' => 'Arizona', 'code' => 'AZ'],
            ['country_code' => 'US', 'name' => 'Arkansas', 'code' => 'AR'],
            ['country_code' => 'US', 'name' => 'California', 'code' => 'CA'],
            ['country_code' => 'US', 'name' => 'Colorado', 'code' => 'CO'],
            ['country_code' => 'US', 'name' => 'Connecticut', 'code' => 'CT'],
            ['country_code' => 'US', 'name' => 'Delaware', 'code' => 'DE'],
            ['country_code' => 'US', 'name' => 'Florida', 'code' => 'FL'],
            ['country_code' => 'US', 'name' => 'Georgia', 'code' => 'GA'],
            ['country_code' => 'US', 'name' => 'Hawaii', 'code' => 'HI'],
            ['country_code' => 'US', 'name' => 'Idaho', 'code' => 'ID'],
            ['country_code' => 'US', 'name' => 'Illinois', 'code' => 'IL'],
            ['country_code' => 'US', 'name' => 'Indiana', 'code' => 'IN'],
            ['country_code' => 'US', 'name' => 'Iowa', 'code' => 'IA'],
            ['country_code' => 'US', 'name' => 'Kansas', 'code' => 'KS'],
            ['country_code' => 'US', 'name' => 'Kentucky', 'code' => 'KY'],
            ['country_code' => 'US', 'name' => 'Louisiana', 'code' => 'LA'],
            ['country_code' => 'US', 'name' => 'Maine', 'code' => 'ME'],
            ['country_code' => 'US', 'name' => 'Maryland', 'code' => 'MD'],
            ['country_code' => 'US', 'name' => 'Massachusetts', 'code' => 'MA'],
            ['country_code' => 'US', 'name' => 'Michigan', 'code' => 'MI'],
            ['country_code' => 'US', 'name' => 'Minnesota', 'code' => 'MN'],
            ['country_code' => 'US', 'name' => 'Mississippi', 'code' => 'MS'],
            ['country_code' => 'US', 'name' => 'Missouri', 'code' => 'MO'],
            ['country_code' => 'US', 'name' => 'Montana', 'code' => 'MT'],
            ['country_code' => 'US', 'name' => 'Nebraska', 'code' => 'NE'],
            ['country_code' => 'US', 'name' => 'Nevada', 'code' => 'NV'],
            ['country_code' => 'US', 'name' => 'New Hampshire', 'code' => 'NH'],
            ['country_code' => 'US', 'name' => 'New Jersey', 'code' => 'NJ'],
            ['country_code' => 'US', 'name' => 'New Mexico', 'code' => 'NM'],
            ['country_code' => 'US', 'name' => 'New York', 'code' => 'NY'],
            ['country_code' => 'US', 'name' => 'North Carolina', 'code' => 'NC'],
            ['country_code' => 'US', 'name' => 'North Dakota', 'code' => 'ND'],
            ['country_code' => 'US', 'name' => 'Ohio', 'code' => 'OH'],
            ['country_code' => 'US', 'name' => 'Oklahoma', 'code' => 'OK'],
            ['country_code' => 'US', 'name' => 'Oregon', 'code' => 'OR'],
            ['country_code' => 'US', 'name' => 'Pennsylvania', 'code' => 'PA'],
            ['country_code' => 'US', 'name' => 'Rhode Island', 'code' => 'RI'],
            ['country_code' => 'US', 'name' => 'South Carolina', 'code' => 'SC'],
            ['country_code' => 'US', 'name' => 'South Dakota', 'code' => 'SD'],
            ['country_code' => 'US', 'name' => 'Tennessee', 'code' => 'TN'],
            ['country_code' => 'US', 'name' => 'Texas', 'code' => 'TX'],
            ['country_code' => 'US', 'name' => 'Utah', 'code' => 'UT'],
            ['country_code' => 'US', 'name' => 'Vermont', 'code' => 'VT'],
            ['country_code' => 'US', 'name' => 'Virginia', 'code' => 'VA'],
            ['country_code' => 'US', 'name' => 'Washington', 'code' => 'WA'],
            ['country_code' => 'US', 'name' => 'West Virginia', 'code' => 'WV'],
            ['country_code' => 'US', 'name' => 'Wisconsin', 'code' => 'WI'],
            ['country_code' => 'US', 'name' => 'Wyoming', 'code' => 'WY'],
            ['country_code' => 'US', 'name' => 'District of Columbia', 'code' => 'DC'],

            // Canada
            ['country_code' => 'CA', 'name' => 'Alberta', 'code' => 'AB'],
            ['country_code' => 'CA', 'name' => 'British Columbia', 'code' => 'BC'],
            ['country_code' => 'CA', 'name' => 'Manitoba', 'code' => 'MB'],
            ['country_code' => 'CA', 'name' => 'New Brunswick', 'code' => 'NB'],
            ['country_code' => 'CA', 'name' => 'Newfoundland and Labrador', 'code' => 'NL'],
            ['country_code' => 'CA', 'name' => 'Northwest Territories', 'code' => 'NT'],
            ['country_code' => 'CA', 'name' => 'Nova Scotia', 'code' => 'NS'],
            ['country_code' => 'CA', 'name' => 'Nunavut', 'code' => 'NU'],
            ['country_code' => 'CA', 'name' => 'Ontario', 'code' => 'ON'],
            ['country_code' => 'CA', 'name' => 'Prince Edward Island', 'code' => 'PE'],
            ['country_code' => 'CA', 'name' => 'Quebec', 'code' => 'QC'],
            ['country_code' => 'CA', 'name' => 'Saskatchewan', 'code' => 'SK'],
            ['country_code' => 'CA', 'name' => 'Yukon', 'code' => 'YT'],

            // Australia
            ['country_code' => 'AU', 'name' => 'Australian Capital Territory', 'code' => 'ACT'],
            ['country_code' => 'AU', 'name' => 'New South Wales', 'code' => 'NSW'],
            ['country_code' => 'AU', 'name' => 'Northern Territory', 'code' => 'NT'],
            ['country_code' => 'AU', 'name' => 'Queensland', 'code' => 'QLD'],
            ['country_code' => 'AU', 'name' => 'South Australia', 'code' => 'SA'],
            ['country_code' => 'AU', 'name' => 'Tasmania', 'code' => 'TAS'],
            ['country_code' => 'AU', 'name' => 'Victoria', 'code' => 'VIC'],
            ['country_code' => 'AU', 'name' => 'Western Australia', 'code' => 'WA'],

            // Japan (prefectures)
            ['country_code' => 'JP', 'name' => 'Hokkaido', 'code' => 'JP-01'],
            ['country_code' => 'JP', 'name' => 'Aomori', 'code' => 'JP-02'],
            ['country_code' => 'JP', 'name' => 'Iwate', 'code' => 'JP-03'],
            ['country_code' => 'JP', 'name' => 'Miyagi', 'code' => 'JP-04'],
            ['country_code' => 'JP', 'name' => 'Akita', 'code' => 'JP-05'],
            ['country_code' => 'JP', 'name' => 'Yamagata', 'code' => 'JP-06'],
            ['country_code' => 'JP', 'name' => 'Fukushima', 'code' => 'JP-07'],
            ['country_code' => 'JP', 'name' => 'Ibaraki', 'code' => 'JP-08'],
            ['country_code' => 'JP', 'name' => 'Tochigi', 'code' => 'JP-09'],
            ['country_code' => 'JP', 'name' => 'Gunma', 'code' => 'JP-10'],
            ['country_code' => 'JP', 'name' => 'Saitama', 'code' => 'JP-11'],
            ['country_code' => 'JP', 'name' => 'Chiba', 'code' => 'JP-12'],
            ['country_code' => 'JP', 'name' => 'Tokyo', 'code' => 'JP-13'],
            ['country_code' => 'JP', 'name' => 'Kanagawa', 'code' => 'JP-14'],
            ['country_code' => 'JP', 'name' => 'Niigata', 'code' => 'JP-15'],
            ['country_code' => 'JP', 'name' => 'Toyama', 'code' => 'JP-16'],
            ['country_code' => 'JP', 'name' => 'Ishikawa', 'code' => 'JP-17'],
            ['country_code' => 'JP', 'name' => 'Fukui', 'code' => 'JP-18'],
            ['country_code' => 'JP', 'name' => 'Yamanashi', 'code' => 'JP-19'],
            ['country_code' => 'JP', 'name' => 'Nagano', 'code' => 'JP-20'],
            ['country_code' => 'JP', 'name' => 'Gifu', 'code' => 'JP-21'],
            ['country_code' => 'JP', 'name' => 'Shizuoka', 'code' => 'JP-22'],
            ['country_code' => 'JP', 'name' => 'Aichi', 'code' => 'JP-23'],
            ['country_code' => 'JP', 'name' => 'Mie', 'code' => 'JP-24'],
            ['country_code' => 'JP', 'name' => 'Shiga', 'code' => 'JP-25'],
            ['country_code' => 'JP', 'name' => 'Kyoto', 'code' => 'JP-26'],
            ['country_code' => 'JP', 'name' => 'Osaka', 'code' => 'JP-27'],
            ['country_code' => 'JP', 'name' => 'Hyogo', 'code' => 'JP-28'],
            ['country_code' => 'JP', 'name' => 'Nara', 'code' => 'JP-29'],
            ['country_code' => 'JP', 'name' => 'Wakayama', 'code' => 'JP-30'],
            ['country_code' => 'JP', 'name' => 'Tottori', 'code' => 'JP-31'],
            ['country_code' => 'JP', 'name' => 'Shimane', 'code' => 'JP-32'],
            ['country_code' => 'JP', 'name' => 'Okayama', 'code' => 'JP-33'],
            ['country_code' => 'JP', 'name' => 'Hiroshima', 'code' => 'JP-34'],
            ['country_code' => 'JP', 'name' => 'Yamaguchi', 'code' => 'JP-35'],
            ['country_code' => 'JP', 'name' => 'Tokushima', 'code' => 'JP-36'],
            ['country_code' => 'JP', 'name' => 'Kagawa', 'code' => 'JP-37'],
            ['country_code' => 'JP', 'name' => 'Ehime', 'code' => 'JP-38'],
            ['country_code' => 'JP', 'name' => 'Kochi', 'code' => 'JP-39'],
            ['country_code' => 'JP', 'name' => 'Fukuoka', 'code' => 'JP-40'],
            ['country_code' => 'JP', 'name' => 'Saga', 'code' => 'JP-41'],
            ['country_code' => 'JP', 'name' => 'Nagasaki', 'code' => 'JP-42'],
            ['country_code' => 'JP', 'name' => 'Kumamoto', 'code' => 'JP-43'],
            ['country_code' => 'JP', 'name' => 'Oita', 'code' => 'JP-44'],
            ['country_code' => 'JP', 'name' => 'Miyazaki', 'code' => 'JP-45'],
            ['country_code' => 'JP', 'name' => 'Kagoshima', 'code' => 'JP-46'],
            ['country_code' => 'JP', 'name' => 'Okinawa', 'code' => 'JP-47'],

            // Malaysia
            ['country_code' => 'MY', 'name' => 'Johor', 'code' => 'JHR'],
            ['country_code' => 'MY', 'name' => 'Kedah', 'code' => 'KDH'],
            ['country_code' => 'MY', 'name' => 'Kelantan', 'code' => 'KTN'],
            ['country_code' => 'MY', 'name' => 'Kuala Lumpur', 'code' => 'KUL'],
            ['country_code' => 'MY', 'name' => 'Labuan', 'code' => 'LBN'],
            ['country_code' => 'MY', 'name' => 'Melaka', 'code' => 'MLK'],
            ['country_code' => 'MY', 'name' => 'Negeri Sembilan', 'code' => 'NSN'],
            ['country_code' => 'MY', 'name' => 'Pahang', 'code' => 'PHG'],
            ['country_code' => 'MY', 'name' => 'Penang', 'code' => 'PNG'],
            ['country_code' => 'MY', 'name' => 'Perak', 'code' => 'PRK'],
            ['country_code' => 'MY', 'name' => 'Perlis', 'code' => 'PLS'],
            ['country_code' => 'MY', 'name' => 'Putrajaya', 'code' => 'PJY'],
            ['country_code' => 'MY', 'name' => 'Sabah', 'code' => 'SBH'],
            ['country_code' => 'MY', 'name' => 'Sarawak', 'code' => 'SWK'],
            ['country_code' => 'MY', 'name' => 'Selangor', 'code' => 'SGR'],
            ['country_code' => 'MY', 'name' => 'Terengganu', 'code' => 'TRG'],

            // United Kingdom
            ['country_code' => 'GB', 'name' => 'England', 'code' => 'ENG'],
            ['country_code' => 'GB', 'name' => 'Northern Ireland', 'code' => 'NIR'],
            ['country_code' => 'GB', 'name' => 'Scotland', 'code' => 'SCT'],
            ['country_code' => 'GB', 'name' => 'Wales', 'code' => 'WLS'],
        ];

        foreach ($zones as $zone) {
            $countryId = $countryIds[$zone['country_code']] ?? null;
            if (!$countryId) continue;

            Zone::updateOrCreate(
                ['country_id' => $countryId, 'code' => $zone['code']],
                [
                    'country_id' => $countryId,
                    'name' => $zone['name'],
                    'code' => $zone['code'],
                ]
            );
        }
    }
}
