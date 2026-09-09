<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createRegionsTable();
        $this->createProvincesTable();
        $this->createCitiesTable();
        $this->createBarangaysTable();

        $this->seedIfEmpty('philippine_regions', 'philippine_regions.sql');
        $this->seedIfEmpty('philippine_provinces', 'philippine_provinces.sql');
        $this->seedIfEmpty('philippine_cities', 'philippine_cities.sql');
        $this->seedIfEmpty('philippine_barangays', 'philippine_barangays.sql');
    }

    public function down(): void
    {
        // The original address migrations own these tables. This repair migration
        // must not remove them when rolled back independently.
    }

    private function createRegionsTable(): void
    {
        if (Schema::hasTable('philippine_regions')) {
            return;
        }

        Schema::create('philippine_regions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('psgc_code')->index();
            $table->string('name');
            $table->string('region_code')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function createProvincesTable(): void
    {
        if (Schema::hasTable('philippine_provinces')) {
            return;
        }

        Schema::create('philippine_provinces', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('psgc_code')->index();
            $table->string('name');
            $table->string('region_code')->index();
            $table->string('province_code')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function createCitiesTable(): void
    {
        if (Schema::hasTable('philippine_cities')) {
            return;
        }

        Schema::create('philippine_cities', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('psgc_code')->index();
            $table->string('name');
            $table->string('region_code')->index();
            $table->string('province_code')->index();
            $table->string('city_code')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function createBarangaysTable(): void
    {
        if (Schema::hasTable('philippine_barangays')) {
            return;
        }

        Schema::create('philippine_barangays', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('psgc_code')->index();
            $table->string('name');
            $table->string('region_code')->index();
            $table->string('province_code')->index();
            $table->string('city_code')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function seedIfEmpty(string $table, string $filename): void
    {
        if (DB::table($table)->exists()) {
            return;
        }

        $sql = file_get_contents(database_path("seeders/sql/{$filename}"));
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Unable to load address seed data: {$filename}");
        }

        DB::unprepared($sql);
    }
};
