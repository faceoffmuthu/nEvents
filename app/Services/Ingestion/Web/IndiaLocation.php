<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Web;

/**
 * Decides whether a discovered event's location is in India, for sources
 * with region_scope = "india" (national platforms). Tamil Nadu districts are
 * resolved separately by DistrictService; this only answers "India or not"
 * and gives a short "City, State" label for events outside Tamil Nadu.
 *
 * Evidence, strongest first: addressCountry, an Indian state / UT name, a
 * known Indian city, the word "India". No evidence = unknown (null) — never
 * assumed to be Indian.
 */
class IndiaLocation
{
    private const STATES = [
        'andhra pradesh' => 'Andhra Pradesh', 'arunachal pradesh' => 'Arunachal Pradesh', 'assam' => 'Assam', 'bihar' => 'Bihar',
        'chhattisgarh' => 'Chhattisgarh', 'goa' => 'Goa', 'gujarat' => 'Gujarat', 'haryana' => 'Haryana', 'himachal pradesh' => 'Himachal Pradesh',
        'jharkhand' => 'Jharkhand', 'karnataka' => 'Karnataka', 'kerala' => 'Kerala', 'madhya pradesh' => 'Madhya Pradesh',
        'maharashtra' => 'Maharashtra', 'manipur' => 'Manipur', 'meghalaya' => 'Meghalaya', 'mizoram' => 'Mizoram', 'nagaland' => 'Nagaland',
        'odisha' => 'Odisha', 'orissa' => 'Odisha', 'punjab' => 'Punjab', 'rajasthan' => 'Rajasthan', 'sikkim' => 'Sikkim',
        'tamil nadu' => 'Tamil Nadu', 'telangana' => 'Telangana', 'tripura' => 'Tripura', 'uttar pradesh' => 'Uttar Pradesh',
        'uttarakhand' => 'Uttarakhand', 'west bengal' => 'West Bengal', 'delhi' => 'Delhi', 'new delhi' => 'Delhi',
        'jammu and kashmir' => 'Jammu and Kashmir', 'ladakh' => 'Ladakh', 'puducherry' => 'Puducherry', 'pondicherry' => 'Puducherry',
        'chandigarh' => 'Chandigarh', 'andaman and nicobar' => 'Andaman and Nicobar Islands', 'lakshadweep' => 'Lakshadweep',
        'dadra and nagar haveli' => 'Dadra and Nagar Haveli and Daman and Diu', 'daman and diu' => 'Dadra and Nagar Haveli and Daman and Diu',
    ];

    /** Major Indian cities (outside the state names above) => state */
    private const CITIES = [
        'mumbai' => 'Maharashtra', 'bombay' => 'Maharashtra', 'pune' => 'Maharashtra', 'nagpur' => 'Maharashtra', 'nashik' => 'Maharashtra', 'thane' => 'Maharashtra', 'navi mumbai' => 'Maharashtra',
        'bengaluru' => 'Karnataka', 'bangalore' => 'Karnataka', 'mysuru' => 'Karnataka', 'mysore' => 'Karnataka', 'mangaluru' => 'Karnataka', 'mangalore' => 'Karnataka', 'hubli' => 'Karnataka',
        'hyderabad' => 'Telangana', 'secunderabad' => 'Telangana', 'warangal' => 'Telangana',
        'visakhapatnam' => 'Andhra Pradesh', 'vizag' => 'Andhra Pradesh', 'vijayawada' => 'Andhra Pradesh', 'tirupati' => 'Andhra Pradesh', 'guntur' => 'Andhra Pradesh',
        'kochi' => 'Kerala', 'cochin' => 'Kerala', 'thiruvananthapuram' => 'Kerala', 'trivandrum' => 'Kerala', 'kozhikode' => 'Kerala', 'calicut' => 'Kerala', 'thrissur' => 'Kerala',
        'kolkata' => 'West Bengal', 'calcutta' => 'West Bengal', 'siliguri' => 'West Bengal', 'durgapur' => 'West Bengal',
        'gurugram' => 'Haryana', 'gurgaon' => 'Haryana', 'faridabad' => 'Haryana', 'panipat' => 'Haryana',
        'noida' => 'Uttar Pradesh', 'greater noida' => 'Uttar Pradesh', 'ghaziabad' => 'Uttar Pradesh', 'lucknow' => 'Uttar Pradesh', 'kanpur' => 'Uttar Pradesh', 'varanasi' => 'Uttar Pradesh', 'agra' => 'Uttar Pradesh', 'prayagraj' => 'Uttar Pradesh',
        'ahmedabad' => 'Gujarat', 'surat' => 'Gujarat', 'vadodara' => 'Gujarat', 'rajkot' => 'Gujarat', 'gandhinagar' => 'Gujarat',
        'jaipur' => 'Rajasthan', 'udaipur' => 'Rajasthan', 'jodhpur' => 'Rajasthan',
        'indore' => 'Madhya Pradesh', 'bhopal' => 'Madhya Pradesh', 'raipur' => 'Chhattisgarh',
        'ludhiana' => 'Punjab', 'amritsar' => 'Punjab', 'mohali' => 'Punjab',
        'bhubaneswar' => 'Odisha', 'cuttack' => 'Odisha', 'patna' => 'Bihar', 'ranchi' => 'Jharkhand', 'jamshedpur' => 'Jharkhand',
        'guwahati' => 'Assam', 'dehradun' => 'Uttarakhand', 'rishikesh' => 'Uttarakhand', 'shimla' => 'Himachal Pradesh',
        'srinagar' => 'Jammu and Kashmir', 'jammu' => 'Jammu and Kashmir', 'panaji' => 'Goa', 'shillong' => 'Meghalaya', 'gangtok' => 'Sikkim',
    ];

    /**
     * @return array{in_india: ?bool, label: ?string}  in_india null = no evidence either way
     */
    public function classify(array $record): array
    {
        $country = mb_strtolower(trim((string) ($record['country_raw'] ?? '')));
        $city    = trim((string) ($record['city_raw'] ?? ''));
        $text    = mb_strtolower(implode(' | ', array_filter([
            $record['city_raw'] ?? null, $record['region_raw'] ?? null, $record['venue_address'] ?? null, $record['venue_name'] ?? null,
        ], 'is_string')));

        if ($country !== '' && !in_array($country, ['in', 'ind', 'india', 'bharat'], true)) {
            return ['in_india' => false, 'label' => null];
        }

        $state = null;
        foreach (self::STATES as $needle => $name) {
            if ($this->has($text, $needle)) { $state = $name; break; }
        }
        $matchedCity = null;
        foreach (self::CITIES as $needle => $cityState) {
            if ($this->has($text, $needle)) { $matchedCity = ucwords($needle); $state ??= $cityState; break; }
        }

        if ($state === null && $country === '' && !$this->has($text, 'india')) {
            return ['in_india' => null, 'label' => null];
        }

        $place = $city !== '' ? $city : $matchedCity;
        $label = implode(', ', array_unique(array_filter([$place, $state])));
        return ['in_india' => true, 'label' => $label !== '' ? mb_substr($label, 0, 150) : 'India'];
    }

    private function has(string $haystack, string $needle): bool
    {
        return preg_match('/(?<![a-z])' . preg_quote($needle, '/') . '(?![a-z])/u', $haystack) === 1;
    }
}
