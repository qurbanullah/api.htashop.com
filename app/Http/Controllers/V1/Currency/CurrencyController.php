<?php

namespace App\Http\Controllers\V1\Currency;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Currency\CurrencyResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        $currencies = Currency::where('is_active', true)->orderBy('name')->get();
        return ApiResponse::success(CurrencyResource::collection($currencies), 'Currencies retrieved');
    }
}
