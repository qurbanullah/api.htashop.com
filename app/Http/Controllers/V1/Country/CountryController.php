<?php

namespace App\Http\Controllers\V1\Country;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Country\CountryResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        $countries = CacheHelper::remember(
            ['countries'],
            'countries:list',
            86400, // 24h — reference data changes rarely
            fn () => Country::query()->orderBy('name')->get(),
        );

        return ApiResponse::success(
            CountryResource::collection($countries),
            'Countries retrieved successfully',
        );
    }
}
