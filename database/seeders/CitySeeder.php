<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'PK' => ['Karachi', 'Lahore', 'Faisalabad', 'Rawalpindi', 'Islamabad', 'Multan', 'Gujranwala', 'Hyderabad', 'Peshawar', 'Quetta', 'Sialkot', 'Sargodha', 'Bahawalpur', 'Sukkur', 'Abbottabad'],
            'SA' => ['Riyadh', 'Jeddah', 'Mecca', 'Medina', 'Dammam', 'Khobar', 'Dhahran', 'Tabuk', 'Abha', 'Taif', 'Yanbu', 'Jubail', 'Buraidah', 'Hail', 'Najran'],
            'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah', 'Umm Al Quwain', 'Al Ain'],
            'QA' => ['Doha', 'Al Rayyan', 'Al Wakrah', 'Al Khor', 'Lusail'],
            'KW' => ['Kuwait City', 'Hawalli', 'Salmiya', 'Al Ahmadi', 'Farwaniya', 'Mubarak Al-Kabeer'],
            'BH' => ['Manama', 'Riffa', 'Muharraq', 'Hamad Town', 'Isa Town', 'Sitra'],
            'OM' => ['Muscat', 'Salalah', 'Sohar', 'Nizwa', 'Sur', 'Rustaq'],
            'US' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose', 'Austin', 'Jacksonville', 'San Francisco', 'Columbus', 'Seattle', 'Denver', 'Boston', 'Miami', 'Atlanta', 'Las Vegas', 'Portland', 'Detroit', 'Memphis', 'Baltimore', 'Milwaukee', 'New Orleans', 'Minneapolis', 'Oklahoma City', 'Tampa', 'Orlando', 'Kansas City', 'Cleveland', 'Pittsburgh', 'St. Louis', 'Charlotte', 'Raleigh', 'Nashville', 'Indianapolis', 'Cincinnati', 'Salt Lake City'],
            'GB' => ['London', 'Manchester', 'Birmingham', 'Leeds', 'Liverpool', 'Glasgow', 'Newcastle', 'Sheffield', 'Bristol', 'Nottingham', 'Cardiff', 'Edinburgh', 'Leicester', 'Belfast', 'Aberdeen', 'Brighton', 'Coventry', 'Hull', 'Oxford', 'Cambridge', 'York', 'Southampton', 'Portsmouth'],
            'CA' => ['Toronto', 'Montreal', 'Vancouver', 'Calgary', 'Edmonton', 'Ottawa', 'Winnipeg', 'Quebec City', 'Hamilton', 'London', 'Victoria', 'Halifax', 'Saskatoon', 'Regina', 'Mississauga'],
            'AU' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Canberra', 'Gold Coast', 'Newcastle', 'Hobart', 'Darwin'],
            'NZ' => ['Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'Tauranga', 'Dunedin'],
            'DE' => ['Berlin', 'Hamburg', 'Munich', 'Cologne', 'Frankfurt', 'Stuttgart', 'Düsseldorf', 'Leipzig', 'Dortmund', 'Essen', 'Bremen', 'Dresden', 'Hannover', 'Nuremberg', 'Bonn'],
            'FR' => ['Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier', 'Bordeaux', 'Lille', 'Rennes', 'Reims'],
            'ES' => ['Madrid', 'Barcelona', 'Valencia', 'Seville', 'Zaragoza', 'Málaga', 'Bilbao', 'Murcia', 'Palma', 'Granada', 'Alicante'],
            'IT' => ['Rome', 'Milan', 'Naples', 'Turin', 'Palermo', 'Genoa', 'Bologna', 'Florence', 'Bari', 'Venice', 'Verona'],
            'NL' => ['Amsterdam', 'Rotterdam', 'The Hague', 'Utrecht', 'Eindhoven', 'Groningen', 'Tilburg', 'Almere'],
            'BE' => ['Brussels', 'Antwerp', 'Ghent', 'Charleroi', 'Liège', 'Bruges'],
            'CH' => ['Zurich', 'Geneva', 'Basel', 'Lausanne', 'Bern', 'Winterthur', 'Lucerne'],
            'AT' => ['Vienna', 'Graz', 'Linz', 'Salzburg', 'Innsbruck'],
            'SE' => ['Stockholm', 'Gothenburg', 'Malmö', 'Uppsala', 'Västerås'],
            'NO' => ['Oslo', 'Bergen', 'Trondheim', 'Stavanger', 'Tromsø'],
            'DK' => ['Copenhagen', 'Aarhus', 'Odense', 'Aalborg', 'Esbjerg'],
            'FI' => ['Helsinki', 'Espoo', 'Tampere', 'Vantaa', 'Oulu', 'Turku'],
            'IE' => ['Dublin', 'Cork', 'Limerick', 'Galway', 'Waterford'],
            'PT' => ['Lisbon', 'Porto', 'Braga', 'Coimbra', 'Funchal', 'Faro'],
            'PL' => ['Warsaw', 'Kraków', 'Łódź', 'Wrocław', 'Poznań', 'Gdańsk', 'Szczecin', 'Katowice'],
            'CZ' => ['Prague', 'Brno', 'Ostrava', 'Plzeň', 'Liberec'],
            'HU' => ['Budapest', 'Debrecen', 'Szeged', 'Miskolc', 'Pécs', 'Győr'],
            'GR' => ['Athens', 'Thessaloniki', 'Patras', 'Heraklion', 'Larissa'],
            'TR' => ['Istanbul', 'Ankara', 'Izmir', 'Bursa', 'Antalya', 'Adana', 'Gaziantep', 'Konya'],
            'RU' => ['Moscow', 'Saint Petersburg', 'Novosibirsk', 'Yekaterinburg', 'Kazan', 'Nizhny Novgorod', 'Samara'],
            'UA' => ['Kyiv', 'Kharkiv', 'Odesa', 'Dnipro', 'Lviv', 'Zaporizhzhia'],
            'RO' => ['Bucharest', 'Cluj-Napoca', 'Timișoara', 'Iași', 'Constanța'],
            'IN' => ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Ahmedabad', 'Chennai', 'Kolkata', 'Pune', 'Jaipur', 'Lucknow', 'Nagpur', 'Indore', 'Surat', 'Kanpur', 'Patna', 'Bhopal', 'Vadodara', 'Coimbatore'],
            'CN' => ['Beijing', 'Shanghai', 'Guangzhou', 'Shenzhen', 'Chengdu', 'Hangzhou', 'Wuhan', 'Xi\'an', 'Nanjing', 'Chongqing', 'Tianjin', 'Suzhou', 'Harbin', 'Qingdao', 'Dalian'],
            'JP' => ['Tokyo', 'Yokohama', 'Osaka', 'Nagoya', 'Sapporo', 'Fukuoka', 'Kobe', 'Kyoto', 'Kawasaki', 'Hiroshima'],
            'KR' => ['Seoul', 'Busan', 'Incheon', 'Daegu', 'Daejeon', 'Gwangju', 'Suwon', 'Ulsan'],
            'SG' => ['Singapore'],
            'MY' => ['Kuala Lumpur', 'George Town', 'Johor Bahru', 'Ipoh', 'Shah Alam', 'Malacca', 'Kuching', 'Kota Kinabalu'],
            'TH' => ['Bangkok', 'Nonthaburi', 'Nakhon Ratchasima', 'Chiang Mai', 'Hat Yai', 'Phuket', 'Pattaya'],
            'VN' => ['Ho Chi Minh City', 'Hanoi', 'Da Nang', 'Hai Phong', 'Can Tho', 'Hue'],
            'ID' => ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Semarang', 'Makassar', 'Palembang', 'Denpasar'],
            'PH' => ['Manila', 'Quezon City', 'Cebu City', 'Davao City', 'Makati', 'Zamboanga City'],
            'HK' => ['Hong Kong'],
            'TW' => ['Taipei', 'Kaohsiung', 'Taichung', 'Tainan', 'Hsinchu'],
            'BR' => ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador', 'Fortaleza', 'Belo Horizonte', 'Manaus', 'Curitiba', 'Recife', 'Porto Alegre'],
            'MX' => ['Mexico City', 'Guadalajara', 'Monterrey', 'Puebla', 'Tijuana', 'León', 'Cancún'],
            'AR' => ['Buenos Aires', 'Córdoba', 'Rosario', 'Mendoza', 'La Plata', 'Mar del Plata'],
            'CL' => ['Santiago', 'Valparaíso', 'Concepción', 'Antofagasta', 'Temuco'],
            'CO' => ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena', 'Bucaramanga'],
            'PE' => ['Lima', 'Arequipa', 'Trujillo', 'Chiclayo', 'Cusco'],
            'VE' => ['Caracas', 'Maracaibo', 'Valencia', 'Barquisimeto'],
            'EG' => ['Cairo', 'Alexandria', 'Giza', 'Shubra El Kheima', 'Port Said', 'Suez', 'Luxor'],
            'NG' => ['Lagos', 'Abuja', 'Kano', 'Ibadan', 'Port Harcourt', 'Benin City', 'Kaduna'],
            'ZA' => ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria', 'Port Elizabeth', 'Bloemfontein'],
            'KE' => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret'],
            'MA' => ['Casablanca', 'Rabat', 'Marrakesh', 'Fes', 'Tangier', 'Agadir', 'Meknes'],
            'TN' => ['Tunis', 'Sfax', 'Sousse', 'Kairouan', 'Bizerte'],
            'DZ' => ['Algiers', 'Oran', 'Constantine', 'Annaba', 'Blida', 'Setif'],
            'IL' => ['Jerusalem', 'Tel Aviv', 'Haifa', 'Rishon LeZion', 'Beersheba'],
            'JO' => ['Amman', 'Zarqa', 'Irbid', 'Aqaba'],
            'LB' => ['Beirut', 'Tripoli', 'Sidon', 'Tyre'],
            'IQ' => ['Baghdad', 'Basra', 'Mosul', 'Erbil', 'Najaf', 'Karbala'],
            'IR' => ['Tehran', 'Mashhad', 'Isfahan', 'Karaj', 'Shiraz', 'Tabriz'],
            'AZ' => ['Baku', 'Ganja', 'Sumqayit', 'Mingachevir'],
            'GE' => ['Tbilisi', 'Batumi', 'Kutaisi', 'Rustavi'],
            'AM' => ['Yerevan', 'Gyumri', 'Vanadzor'],
            'KZ' => ['Almaty', 'Astana', 'Shymkent', 'Karaganda', 'Aktau'],
            'UZ' => ['Tashkent', 'Samarkand', 'Bukhara', 'Namangan', 'Andijan'],
            'BD' => ['Dhaka', 'Chittagong', 'Khulna', 'Rajshahi', 'Sylhet', 'Barishal'],
            'LK' => ['Colombo', 'Kandy', 'Galle', 'Jaffna', 'Negombo'],
            'NP' => ['Kathmandu', 'Pokhara', 'Lalitpur', 'Biratnagar'],
        ];

        $byCode = Country::query()->pluck('id', 'code')->all();

        $count = 0;
        foreach ($cities as $code => $names) {
            if (empty($names) || ! isset($byCode[$code])) {
                continue;
            }
            foreach ($names as $name) {
                City::updateOrCreate(
                    ['country_id' => $byCode[$code], 'name' => $name],
                    ['country_id' => $byCode[$code], 'name' => $name],
                );
                $count++;
            }
        }

        $this->command->info("Seeded {$count} cities.");
    }
}
