<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link the Philippine address tables in a strict hierarchy:
     * region -> province -> city -> barangay. The reference data shipped by the
     * 1995_10_23_* migrations has no orphans (verified before applying), but a
     * defensive sanitise pass nulls out any stray code anyway so the new
     * constraints cannot fail on legacy rows.
     */
    public function up(): void
    {
        $orphans = [
            'UPDATE philippine_provinces p LEFT JOIN philippine_regions r ON r.region_code = p.region_code SET p.region_code = NULL WHERE r.id IS NULL',
            'UPDATE philippine_cities c LEFT JOIN philippine_provinces p ON p.province_code = c.province_code SET c.province_code = NULL WHERE p.id IS NULL',
            'UPDATE philippine_barangays b LEFT JOIN philippine_cities c ON c.city_code = b.city_code SET b.city_code = NULL WHERE c.id IS NULL',
        ];

        foreach ($orphans as $statements) {
            DB::statement($statements);
        }

        Schema::table('philippine_provinces', function (Blueprint $table): void {
            $table->foreign('region_code')
                ->references('region_code')
                ->on('philippine_regions')
                ->restrictOnDelete();
        });

        Schema::table('philippine_cities', function (Blueprint $table): void {
            $table->foreign('province_code')
                ->references('province_code')
                ->on('philippine_provinces')
                ->restrictOnDelete();
        });

        Schema::table('philippine_barangays', function (Blueprint $table): void {
            $table->foreign('city_code')
                ->references('city_code')
                ->on('philippine_cities')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('philippine_barangays', function (Blueprint $table): void {
            $table->dropForeign(['city_code']);
        });

        Schema::table('philippine_cities', function (Blueprint $table): void {
            $table->dropForeign(['province_code']);
        });

        Schema::table('philippine_provinces', function (Blueprint $table): void {
            $table->dropForeign(['region_code']);
        });
    }
};
