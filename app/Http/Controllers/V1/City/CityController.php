<?php

namespace App\Http\Controllers\V1\City;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\City\CityResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id');

        abort_if(! $countryId, 422, 'The country_id field is required.');

        $cities = CacheHelper::remember(
            ['cities'],
            "cities:country:{$countryId}",
            86400,
            fn () => City::query()
                ->where('country_id', $countryId)
                ->orderBy('name')
                ->get(),
        );

        return ApiResponse::success(
            CityResource::collection($cities),
            'Cities retrieved successfully',
        );
    }
}
