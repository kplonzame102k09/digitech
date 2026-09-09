<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function regions(): JsonResponse
    {
        return response()->json(
            DB::table('philippine_regions')
                ->orderBy('name')
                ->get(['region_code', 'name'])
        );
    }

    public function provinces(string $regionCode): JsonResponse
    {
        return response()->json(
            DB::table('philippine_provinces')
                ->where('region_code', $regionCode)
                ->orderBy('name')
                ->get(['province_code', 'name'])
        );
    }

    public function cities(string $provinceCode): JsonResponse
    {
        return response()->json(
            DB::table('philippine_cities')
                ->where('province_code', $provinceCode)
                ->orderBy('name')
                ->get(['city_code', 'name'])
        );
    }

    public function barangays(string $cityCode): JsonResponse
    {
        return response()->json(
            DB::table('philippine_barangays')
                ->where('city_code', $cityCode)
                ->orderBy('name')
                ->get(['psgc_code as barangay_code', 'name'])
        );
    }
}
