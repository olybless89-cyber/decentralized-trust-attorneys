<?php
/**
 * Countries and their states/provinces/regions, used for BOTH the personal
 * address fields and the Formation Jurisdiction fields on the application
 * form. Countries without a listed set fall back to a free-text
 * "State / Region" input. Add more countries any time by adding a new
 * "Country Name" => [...] entry to countries_with_states().
 */
function countries_with_states(): array {
    return [
        'United States' => [
            'Alabama','Alaska','Arizona','Arkansas','California','Colorado','Connecticut','Delaware',
            'Florida','Georgia','Hawaii','Idaho','Illinois','Indiana','Iowa','Kansas','Kentucky',
            'Louisiana','Maine','Maryland','Massachusetts','Michigan','Minnesota','Mississippi','Missouri',
            'Montana','Nebraska','Nevada','New Hampshire','New Jersey','New Mexico','New York',
            'North Carolina','North Dakota','Ohio','Oklahoma','Oregon','Pennsylvania','Rhode Island',
            'South Carolina','South Dakota','Tennessee','Texas','Utah','Vermont','Virginia','Washington',
            'West Virginia','Wisconsin','Wyoming','District of Columbia',
        ],
        'Canada' => [
            'Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland and Labrador',
            'Northwest Territories','Nova Scotia','Nunavut','Ontario','Prince Edward Island','Quebec',
            'Saskatchewan','Yukon',
        ],
        'Nigeria' => [
            'Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River',
            'Delta','Ebonyi','Edo','Ekiti','Enugu','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina',
            'Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau',
            'Rivers','Sokoto','Taraba','Yobe','Zamfara','Federal Capital Territory',
        ],
        'United Kingdom' => [
            'England','Scotland','Wales','Northern Ireland',
        ],
        'Australia' => [
            'New South Wales','Victoria','Queensland','Western Australia','South Australia',
            'Tasmania','Australian Capital Territory','Northern Territory',
        ],
        'India' => [
            'Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat',
            'Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra',
            'Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim',
            'Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Delhi',
        ],
        'South Africa' => [
            'Eastern Cape','Free State','Gauteng','KwaZulu-Natal','Limpopo','Mpumalanga',
            'Northern Cape','North West','Western Cape',
        ],
        'Ghana' => [
            'Ahafo','Ashanti','Bono','Bono East','Central','Eastern','Greater Accra','North East',
            'Northern','Oti','Savannah','Upper East','Upper West','Volta','Western','Western North',
        ],
        'Kenya' => [
            'Nairobi','Mombasa','Kisumu','Nakuru','Uasin Gishu','Kiambu','Machakos','Kajiado','Other County',
        ],
        'United Arab Emirates' => [
            'Abu Dhabi','Dubai','Sharjah','Ajman','Umm Al Quwain','Ras Al Khaimah','Fujairah',
        ],
        'Germany' => [
            'Baden-Württemberg','Bavaria','Berlin','Brandenburg','Bremen','Hamburg','Hesse',
            'Lower Saxony','Mecklenburg-Vorpommern','North Rhine-Westphalia','Rhineland-Palatinate',
            'Saarland','Saxony','Saxony-Anhalt','Schleswig-Holstein','Thuringia',
        ],
        'Mexico' => [
            'Aguascalientes','Baja California','Chihuahua','Ciudad de México','Guanajuato','Jalisco',
            'Nuevo León','Puebla','Querétaro','Quintana Roo','Yucatán','Other State',
        ],
        'Brazil' => [
            'São Paulo','Rio de Janeiro','Minas Gerais','Bahia','Paraná','Rio Grande do Sul',
            'Pernambuco','Ceará','Santa Catarina','Distrito Federal','Other State',
        ],
    ];
}

/** Full alphabetical list of country names for Country dropdowns (personal address + jurisdiction). */
function all_countries(): array {
    $withStates = array_keys(countries_with_states());
    $others = [
        'Afghanistan','Albania','Algeria','Andorra','Angola','Argentina','Armenia','Austria',
        'Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize',
        'Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brunei','Bulgaria',
        'Burkina Faso','Burundi','Cabo Verde','Cambodia','Cameroon','Central African Republic',
        'Chad','Chile','China','Colombia','Comoros','Congo (Brazzaville)','Congo (DRC)','Costa Rica',
        'Croatia','Cuba','Cyprus','Czechia','Denmark','Djibouti','Dominica','Dominican Republic',
        'Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini',
        'Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Greece','Grenada',
        'Guatemala','Guinea','Guinea-Bissau','Guyana','Haiti','Honduras','Hong Kong','Hungary',
        'Iceland','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Ivory Coast','Jamaica',
        'Japan','Jordan','Kazakhstan','Kiribati','Kosovo','Kuwait','Kyrgyzstan','Laos','Latvia',
        'Lebanon','Lesotho','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar',
        'Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Mauritania','Mauritius',
        'Micronesia','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar',
        'Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger','North Korea',
        'North Macedonia','Norway','Oman','Pakistan','Palau','Palestine','Panama','Papua New Guinea',
        'Paraguay','Peru','Philippines','Poland','Portugal','Qatar','Romania','Russia','Rwanda',
        'Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino',
        'Sao Tome and Principe','Saudi Arabia','Senegal','Serbia','Seychelles','Sierra Leone',
        'Singapore','Slovakia','Slovenia','Solomon Islands','Somalia','South Korea','South Sudan',
        'Spain','Sri Lanka','Sudan','Suriname','Sweden','Switzerland','Syria','Taiwan','Tajikistan',
        'Tanzania','Thailand','Timor-Leste','Togo','Tonga','Trinidad and Tobago','Tunisia','Turkey',
        'Turkmenistan','Tuvalu','Uganda','Ukraine','Uruguay','Uzbekistan','Vanuatu','Vatican City',
        'Venezuela','Vietnam','Yemen','Zambia','Zimbabwe','Other',
    ];
    $all = array_unique(array_merge($withStates, $others));
    sort($all);
    // Keep "Other" at the very end regardless of alphabetical sort
    $all = array_values(array_diff($all, ['Other']));
    $all[] = 'Other';
    return $all;
}

/**
 * Countries commonly used as business-formation jurisdictions get a
 * "recommended" flag so they can be shown at the top of the Formation
 * Jurisdiction dropdown, ahead of the full A-Z list — the client can
 * still pick literally any country/state as the jurisdiction.
 */
function recommended_jurisdictions(): array {
    return ['United States', 'United Kingdom', 'Canada', 'United Arab Emirates', 'Singapore', 'Hong Kong'];
}
