<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the centroid work down to the barangay level so the admin live
     * map can pin each user at their registered barangay instead of the city
     * centroid. Also corrects two city stamps from the previous migration that
     * were plain wrong (NAGA/Zamboanga Sibugay and MAIGO pointed at unrelated
     * coordinates) and adds the missing LUCENA CITY entry, which is why the
     * administrator account's pin previously fell through to a province-level
     * location far off on the wrong island.
     *
     * Every coordinate below was resolved once, up front, from OSM Nominatim
     * (barangay village/landmark results cross-checked against the PSGC city
     * context) and is stamped here — not via a seeder — so it applies through
     * the live `setup_seed.php` migrate step and survives a db:seed re-run.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('philippine_barangays', 'latitude')) {
            Schema::table('philippine_barangays', function (Blueprint $blueprint): void {
                $blueprint->decimal('latitude', 10, 7)->nullable();
                $blueprint->decimal('longitude', 10, 7)->nullable();
            });
        }

        $this->stampBarangays();
        $this->correctCities();
    }

    public function down(): void
    {
        if (Schema::hasColumn('philippine_barangays', 'latitude')) {
            Schema::table('philippine_barangays', function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['latitude', 'longitude']);
            });
        }

        // Restore the values applied by the previous centroid migration so a
        // rollback returns the reference tables to that exact state.
        $restore = [
            '045624000' => [null, null],             // LUCENA CITY was unstamped
            '052004000' => [13.9844, 124.1353],      // CARAMORAN
            '071214000' => [9.9617, 124.0750],       // CLARIN
            '098309000' => [7.3287, 122.7490],       // NAGA (original, incorrect)
            '101803000' => [9.1554, 124.7825],       // MAHINOG
            '103512000' => [8.1314, 123.7114],       // MAIGO (original, incorrect)
            '175909000' => [12.4140, 122.0043],      // ODIONGAN
        ];

        foreach ($restore as $psgc => [$lat, $lon]) {
            DB::table('philippine_cities')->where('psgc_code', $psgc)
                ->update(['latitude' => $lat, 'longitude' => $lon]);
        }
    }

    private function stampBarangays(): void
    {
        $centroids = [
            '045624020' => [13.9471210, 121.6277347], // Ibabang Dupay, Lucena City, Quezon
            '045624028' => [13.9495686, 121.6351925], // Mayao Kanluran, Lucena City, Quezon
            '052004009' => [13.8782201, 124.1358411], // Datag West, Caramoran, Catanduanes
            '071214017' => [9.9692989, 124.0489074],  // Nahawan, Clarin, Bohol
            '098309008' => [7.8330273, 122.6946098],  // Lower Sulitan, Naga, Zamboanga Sibugay
            '101803011' => [9.1305102, 124.7838378],  // San Miguel, Mahinog, Camiguin
            '103512013' => [8.1314003, 123.9308211],  // Kulasihan (Villanueva), Maigo, Lanao del Norte
            '175909015' => [12.3464458, 121.9752701], // Malilico, Odiongan, Romblon
        ];

        foreach ($centroids as $psgc => [$lat, $lon]) {
            DB::table('philippine_barangays')->where('psgc_code', $psgc)
                ->update(['latitude' => $lat, 'longitude' => $lon]);
        }
    }

    private function correctCities(): void
    {
        $centroids = [
            '045624000' => [13.9268764, 121.6135034], // LUCENA CITY, Quezon
            '052004000' => [13.9838351, 124.1338718], // CARAMORAN, Catanduanes
            '071214000' => [9.9626104, 124.0244228],  // CLARIN, Bohol
            '098309000' => [7.7953039, 122.6942596],  // NAGA, Zamboanga Sibugay (was 7.3287,122.7490)
            '101803000' => [9.1559401, 124.7876921],  // MAHINOG, Camiguin
            '103512000' => [8.1586175, 123.9610824],  // MAIGO, Lanao del Norte (was 8.1314,123.7114)
            '175909000' => [12.3997875, 121.9819686], // ODIONGAN, Romblon
        ];

        foreach ($centroids as $psgc => [$lat, $lon]) {
            DB::table('philippine_cities')->where('psgc_code', $psgc)
                ->update(['latitude' => $lat, 'longitude' => $lon]);
        }
    }
};