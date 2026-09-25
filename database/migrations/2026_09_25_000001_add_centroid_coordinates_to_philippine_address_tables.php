<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add approximate centroid coordinates to the Philippine reference tables
     * so a user's registered region/province/city can be pinned on the admin
     * map as a fallback when GPS/IP do not resolve. Barangays intentionally
     * stay coordinate-less (42k+ rows); they only inform the address label.
     * All coordinates are rounded approximations, which matches the
     * "locations are approximate" label already shown on the map.
     *
     * Centroids are stamped here (not via a seeder) so they apply through the
     * live setup_seed.php `migrate` step. A `db:seed --force` re-run would
     * otherwise re-insert the duplicate-id barangay/city SQL rows and fail
     * before any centroid seeder could run.
     */
    public function up(): void
    {
        foreach (['philippine_regions', 'philippine_provinces', 'philippine_cities'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->decimal('latitude', 10, 7)->nullable();
                $blueprint->decimal('longitude', 10, 7)->nullable();
            });
        }

        $this->stampRegions();
        $this->stampProvinces();
        $this->stampCities();
    }

    public function down(): void
    {
        foreach (['philippine_regions', 'philippine_provinces', 'philippine_cities'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['latitude', 'longitude']);
            });
        }
    }

    private function stampRegions(): void
    {
        $centroids = [
            'REGION I (ILOCOS REGION)' => [16.68, 120.48],
            'REGION II (CAGAYAN VALLEY)' => [17.42, 121.60],
            'REGION III (CENTRAL LUZON)' => [15.50, 120.85],
            'REGION IV-A (CALABARZON)' => [14.10, 121.30],
            'REGION V (BICOL REGION)' => [13.55, 123.25],
            'REGION VI (WESTERN VISAYAS)' => [11.05, 122.50],
            'REGION VII (CENTRAL VISAYAS)' => [10.20, 123.70],
            'REGION VIII (EASTERN VISAYAS)' => [11.70, 125.00],
            'REGION IX (ZAMBOANGA PENINSULA)' => [7.85, 123.10],
            'REGION X (NORTHERN MINDANAO)' => [8.40, 124.60],
            'REGION XI (DAVAO REGION)' => [7.40, 126.00],
            'REGION XII (SOCCSKSARGEN)' => [6.90, 124.70],
            'NATIONAL CAPITAL REGION (NCR)' => [14.60, 121.02],
            'CORDILLERA ADMINISTRATIVE REGION (CAR)' => [17.20, 120.90],
            'BANGSAMORO AUTONOMOUS REGION IN MUSLIM MINDANAO (BARMM)' => [7.10, 124.30],
            'REGION XIII (Caraga)' => [9.10, 125.60],
            'REGION IV-B (MIMAROPA)' => [12.30, 121.20],
        ];

        foreach ($centroids as $name => [$lat, $lon]) {
            DB::table('philippine_regions')->where('name', $name)
                ->update(['latitude' => $lat, 'longitude' => $lon]);
        }
    }

    private function stampProvinces(): void
    {
        $centroids = [
            'ILOCOS NORTE' => [18.16, 120.59],
            'ILOCOS SUR' => [17.23, 120.52],
            'LA UNION' => [16.55, 120.39],
            'PANGASINAN' => [15.92, 120.35],
            'BATANES' => [20.45, 121.97],
            'CAGAYAN' => [17.85, 121.70],
            'ISABELA' => [16.85, 121.75],
            'NUEVA VIZCAYA' => [16.36, 121.03],
            'QUIRINO' => [16.30, 121.55],
            'BATAAN' => [14.60, 120.46],
            'BULACAN' => [14.90, 120.92],
            'NUEVA ECIJA' => [15.55, 120.98],
            'PAMPANGA' => [15.03, 120.62],
            'TARLAC' => [15.50, 120.60],
            'ZAMBALES' => [15.30, 120.10],
            'AURORA' => [15.76, 121.57],
            'BATANGAS' => [13.90, 121.05],
            'CAVITE' => [14.25, 120.83],
            'LAGUNA' => [14.30, 121.40],
            'QUEZON' => [13.90, 122.10],
            'RIZAL' => [14.60, 121.25],
            'ALBAY' => [13.25, 123.65],
            'CAMARINES NORTE' => [14.10, 122.92],
            'CAMARINES SUR' => [13.65, 123.25],
            'CATANDUANES' => [13.84, 124.14],
            'MASBATE' => [12.10, 123.25],
            'SORSOGON' => [12.90, 123.75],
            'AKLAN' => [11.65, 122.40],
            'ANTIQUE' => [11.25, 122.00],
            'CAPIZ' => [11.45, 122.70],
            'ILOILO' => [11.00, 122.55],
            'NEGROS OCCIDENTAL' => [10.40, 123.00],
            'GUIMARAS' => [10.55, 122.60],
            'BOHOL' => [9.90, 124.15],
            'CEBU' => [10.20, 123.75],
            'NEGROS ORIENTAL' => [9.80, 123.10],
            'SIQUIJOR' => [9.20, 123.55],
            'EASTERN SAMAR' => [11.80, 125.40],
            'LEYTE' => [11.00, 124.85],
            'NORTHERN SAMAR' => [12.45, 124.65],
            'SAMAR (WESTERN SAMAR)' => [11.80, 125.00],
            'SOUTHERN LEYTE' => [10.30, 125.05],
            'BILIRAN' => [11.60, 124.55],
            'ZAMBOANGA DEL NORTE' => [8.10, 123.10],
            'ZAMBOANGA DEL SUR' => [7.70, 123.25],
            'ZAMBOANGA SIBUGAY' => [7.60, 122.90],
            'CITY OF ISABELA' => [6.70, 121.99],
            'BUKIDNON' => [8.00, 125.05],
            'CAMIGUIN' => [9.10, 124.70],
            'LANAO DEL NORTE' => [8.10, 123.95],
            'MISAMIS OCCIDENTAL' => [8.40, 123.65],
            'MISAMIS ORIENTAL' => [8.70, 124.95],
            'DAVAO DEL NORTE' => [7.55, 125.70],
            'DAVAO DEL SUR' => [6.95, 125.45],
            'DAVAO ORIENTAL' => [7.35, 126.15],
            'DAVAO DE ORO' => [7.37, 125.93],
            'DAVAO OCCIDENTAL' => [6.50, 125.70],
            'COTABATO (NORTH COTABATO)' => [7.20, 124.75],
            'SOUTH COTABATO' => [6.35, 124.95],
            'SULTAN KUDARAT' => [6.60, 124.55],
            'SARANGANI' => [6.10, 125.20],
            'COTABATO CITY' => [7.22, 124.25],
            'NCR, CITY OF MANILA, FIRST DISTRICT' => [14.60, 120.98],
            'CITY OF MANILA' => [14.60, 120.98],
            'NCR, SECOND DISTRICT' => [14.60, 121.04],
            'NCR, THIRD DISTRICT' => [14.61, 121.02],
            'NCR, FOURTH DISTRICT' => [14.57, 121.04],
            'ABRA' => [17.63, 120.78],
            'BENGUET' => [16.60, 120.70],
            'IFUGAO' => [16.80, 121.10],
            'KALINGA' => [17.42, 121.20],
            'MOUNTAIN PROVINCE' => [17.03, 120.98],
            'APAYAO' => [17.65, 121.25],
            'BASILAN' => [6.50, 122.15],
            'LANAO DEL SUR' => [7.80, 124.20],
            'MAGUINDANAO' => [7.10, 124.55],
            'SULU' => [6.10, 121.10],
            'TAWI-TAWI' => [5.30, 120.25],
            'AGUSAN DEL NORTE' => [9.00, 125.60],
            'AGUSAN DEL SUR' => [8.60, 125.85],
            'SURIGAO DEL NORTE' => [9.70, 125.75],
            'SURIGAO DEL SUR' => [9.20, 126.00],
            'DINAGAT ISLANDS' => [10.02, 125.60],
            'MARINDUQUE' => [13.45, 121.98],
            'OCCIDENTAL MINDORO' => [13.05, 120.90],
            'ORIENTAL MINDORO' => [12.85, 121.35],
            'PALAWAN' => [10.10, 118.90],
            'ROMBLON' => [12.45, 122.20],
        ];

        foreach ($centroids as $name => [$lat, $lon]) {
            DB::table('philippine_provinces')->where('name', $name)
                ->update(['latitude' => $lat, 'longitude' => $lon]);
        }
    }

    private function stampCities(): void
    {
        // Keyed by city name + the province name it belongs to, so preferred
        // municipalities win even when several provinces share a city name
        // (CLARIN is in Bohol as well as Misamis Occidental; NAGA appears in
        // three regions). The province code is resolved at stamp time so no
        // hardcoded codes drift from the reference data.
        $centroids = [
            'LAPUYAN|ZAMBOANGA DEL SUR' => [7.6367, 123.2008],
            'CARAMORAN|CATANDUANES' => [13.9844, 124.1353],
            'CLARIN|BOHOL' => [9.9617, 124.0750],
            'ODIONGAN|ROMBLON' => [12.4140, 122.0043],
            'NAGA|ZAMBOANGA SIBUGAY' => [7.3287, 122.7490],
            'MAIGO|LANAO DEL NORTE' => [8.1314, 123.7114],
            'MAHINOG|CAMIGUIN' => [9.1554, 124.7825],
        ];

        foreach ($centroids as $key => [$lat, $lon]) {
            [$cityName, $provinceName] = explode('|', $key, 2);

            $provinceCode = DB::table('philippine_provinces')
                ->where('name', $provinceName)
                ->value('province_code');

            if (! is_string($provinceCode) || $provinceCode === '') {
                continue;
            }

            DB::table('philippine_cities')
                ->where('name', $cityName)
                ->where('province_code', $provinceCode)
                ->update(['latitude' => $lat, 'longitude' => $lon]);
        }
    }
};