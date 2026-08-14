<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Check if a slug or store name is available.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:255'],
            'store_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = [];

        if (isset($validated['slug'])) {
            $slug = $validated['slug'];
            $tenantExists = Tenant::where('slug', $slug)->exists();
            $orgExists = Organization::where('slug', $slug)->exists();
            $result['slug'] = [
                'available' => !$tenantExists && !$orgExists,
                'message' => (!$tenantExists && !$orgExists)
                    ? 'This URL is available!'
                    : 'This URL is already taken.',
            ];
        }

        if (isset($validated['store_name'])) {
            $exists = Tenant::where('name', $validated['store_name'])->exists()
                || Organization::where('name', $validated['store_name'])->exists();
            $result['store_name'] = [
                'available' => !$exists,
                'message' => !$exists
                    ? 'This name is available!'
                    : 'This name is already taken.',
            ];
        }

        return ApiResponse::success($result, 'Availability check completed.');
    }
}
