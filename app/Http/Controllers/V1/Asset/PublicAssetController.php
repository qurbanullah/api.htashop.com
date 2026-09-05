<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Asset;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Dam\DamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for generating public asset URLs.
 *
 * This controller handles public image URL generation for assets stored in S3.
 * Only the `images/` directory is treated as public and accessible without authentication.
 */
class PublicAssetController extends Controller
{
    public function __construct(protected DamService $damService)
    {
    }

    /**
     * Generate signed URL for public images (public endpoint - no auth required)
     *
     * Only allows paths starting with 'images/' for security.
     * This includes:
     * - images/softwares/icons/
     * - images/softwares/images/
     * - images/softwares/icons/
     * - images/softwares/images/
     * - images/posts/featured/
     * - images/posts/images/
     * - images/versions/
     * - images/changelogs/
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateUrl(\App\Http\Requests\V1\Asset\GenerateUrlRequest $request): JsonResponse
    {
        

        $key = $request->input('key');
        $expiresIn = (int) $request->input('expires_in', 3600);

        // Security: Only allow paths starting with 'images/'
        if (!str_starts_with($key, 'images/')) {
            return ApiResponse::error(
                'Invalid image path. Only images in the images/ directory are accessible.',
                null,
                403
            );
        }

        try {
            $signedUrl = $this->damService->generateAccessUrl($key, $expiresIn);

            if ($signedUrl === null) {
                return ApiResponse::error('File not found', null, 404);
            }

            return ApiResponse::success(['url' => $signedUrl['url']], 'Public asset URL generated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate URL', ['error' => $e->getMessage()], 500);
        }
    }
}
