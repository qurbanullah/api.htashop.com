<?php

namespace App\Http\Controllers\V1\Highlight;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Highlight\HighlightResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Highlight;
use App\Services\Highlight\HighlightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HighlightController extends Controller
{
    public function __construct(
        protected HighlightService $highlightService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $highlights = $this->highlightService->list([
            'search' => $request->string('search')->toString(),
            'category_ids' => $this->arrayParam($request, 'category_ids'),
            'per_page' => (int) $request->integer('per_page', 20),
        ]);

        return ApiResponse::success(
            [
                'data' => HighlightResource::collection($highlights->items())->resolve(),
                'meta' => [
                    'current_page' => $highlights->currentPage(),
                    'last_page' => $highlights->lastPage(),
                    'per_page' => $highlights->perPage(),
                    'total' => $highlights->total(),
                    'from' => $highlights->firstItem(),
                    'to' => $highlights->lastItem(),
                ],
            ],
            'Highlights retrieved successfully',
        );
    }

    public function show(Highlight $highlight): JsonResponse
    {
        return ApiResponse::success(
            new HighlightResource($highlight->load('categories')),
            'Highlight retrieved successfully',
        );
    }

    public function available(Request $request): JsonResponse
    {
        $highlights = $this->highlightService->available([
            'category_ids' => $this->arrayParam($request, 'category_ids'),
            'only_active' => true,
            'per_page' => 200,
        ]);

        return ApiResponse::success(
            ['data' => HighlightResource::collection($highlights->items())->resolve()],
            'Available highlights retrieved successfully',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        return ApiResponse::success(
            new HighlightResource($this->highlightService->create($data)),
            'Highlight created successfully',
            201,
        );
    }

    public function storeForMerchant(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        return ApiResponse::success(
            new HighlightResource($this->highlightService->createMerchant($data)),
            'Highlight created successfully',
            201,
        );
    }

    public function update(Request $request, Highlight $highlight): JsonResponse
    {
        $data = $request->validate($this->rules());

        return ApiResponse::success(
            new HighlightResource($this->highlightService->update($highlight, $data)),
            'Highlight updated successfully',
        );
    }

    public function destroy(Highlight $highlight): JsonResponse
    {
        $this->highlightService->delete($highlight);

        return ApiResponse::success(null, 'Highlight deleted successfully');
    }

    private function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    private function arrayParam(Request $request, string $key): array
    {
        $value = $request->input($key);

        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }

        return [];
    }
}
