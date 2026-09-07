<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function provinces($regionCode)
    {
        $provinces = DB::table('philippine_provinces')
            ->where('region_code', $regionCode)
            ->get(['province_code', 'name']);

        return response()->json($provinces);
    }

    public function cities($provinceCode)
    {
        $cities = DB::table('philippine_cities')
            ->where('province_code', $provinceCode)
            ->get(['city_code', 'name']);

        return response()->json($cities);
    }

    public function barangays($cityCode)
    {
        $barangays = DB::table('philippine_barangays')
            ->where('city_code', $cityCode)
            ->get(['psgc_code as barangay_code', 'name']);

        return response()->json($barangays);
    }
}   