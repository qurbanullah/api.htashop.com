<?php

namespace App\Http\Controllers\V1\ProductReview;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductReview\ReviewResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\ProductReview\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(
        protected ProductReviewService $productReviewService,
    ) {
    }

    public function index(Request $request, string $key): JsonResponse
    {
        $reviews = $this->productReviewService->list($key, (int) $request->integer('per_page', 10));

        return ApiResponse::success(
            [
                'data' => ReviewResource::collection($reviews->items())->resolve(),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'from' => $reviews->firstItem(),
                    'to' => $reviews->lastItem(),
                ],
            ],
            'Reviews retrieved successfully',
        );
    }

    public function summary(string $key): JsonResponse
    {
        return ApiResponse::success(
            $this->productReviewService->summary($key),
            'Review summary retrieved successfully',
        );
    }

    public function store(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'is_recommended' => ['nullable', 'boolean'],
        ]);

        $review = $this->productReviewService->create($key, $data, $request->user('api'));

        return ApiResponse::success(
            new ReviewResource($review->load('user')),
            'Review submitted successfully',
            201,
        );
    }

    public function toggleHelpful(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        return ApiResponse::success(
            $this->productReviewService->vote($uuid, $request->user('api'), (bool) $data['helpful']),
            'Vote recorded successfully',
        );
    }
}
