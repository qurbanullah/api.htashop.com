<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Actions\Punchout\ResolvePunchoutPriceAction;
use App\Actions\Punchout\SearchPunchoutProductsAction;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\PunchoutCatalogIndexRequest;
use App\Http\Requests\V1\Punchout\PunchoutCatalogSuggestRequest;
use App\Http\Resources\V1\Punchout\PunchoutProductDetailResource;
use App\Http\Resources\V1\Punchout\PunchoutProductResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Product;
use App\Models\PunchoutSession;
use App\Models\Tenant;
use App\Services\Catalog\CatalogService;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PunchoutCatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService,
        protected ResolvePunchoutPriceAction $resolvePunchoutPriceAction,
        protected SearchPunchoutProductsAction $searchPunchoutProductsAction,
        protected ProductSearchService $productSearchService,
    ) {}

    public function products(PunchoutCatalogIndexRequest $request): JsonResponse
    {
        $session = $this->session($request);

        $filters = $request->validated();
        $filters['channel'] = SalesChannel::PUNCHOUT;
        $filters['tenant_id'] = $session->tenant_id;

        $products = $this->searchPunchoutProductsAction->handle($filters, $session->buyer);
        $pricing = $this->resolvePunchoutPriceAction->resolveForProducts(
            $this->tenant($session),
            $session->buyer,
            $products->items(),
        );

        return ApiResponse::success([
            'data' => collect($products->items())
                ->map(fn ($product) => (new PunchoutProductResource($product, $pricing[$product->id] ?? []))->resolve())
                ->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ], 'Punchout products retrieved successfully');
    }

    /**
     * Typeahead for the catalog search bar.
     *
     * Typesense only, deliberately: the buyer part-number lookup and per-buyer
     * pricing are skipped here so typing never costs a pricing-table query. The
     * full search still runs those on submit.
     */
    public function suggest(PunchoutCatalogSuggestRequest $request): JsonResponse
    {
        $session = $this->session($request);
        $query = trim((string) $request->validated('q'));

        if (mb_strlen($query) < 2) {
            return ApiResponse::success(['data' => []], 'Punchout suggestions retrieved successfully');
        }

        $filters = [
            'channel' => SalesChannel::PUNCHOUT,
            'tenant_id' => $session->tenant_id,
        ];

        $categoryId = $request->validated('category_id');
        $categoryId = $categoryId === null ? null : (int) $categoryId;

        try {
            $hits = $this->productSearchService->suggest($query, 8, $categoryId, $filters);
        } catch (Throwable $e) {
            Log::warning('Punchout suggestions fell back to the database: '.$e->getMessage(), [
                'tenant_id' => $session->tenant_id,
            ]);

            $hits = $this->productSearchService->fallbackSuggest($query, 8, $categoryId, $filters);
        }

        $ids = array_values(array_filter(array_map(
            static fn (array $hit): int => (int) ($hit['id'] ?? 0),
            $hits,
        )));

        if ($ids === []) {
            return ApiResponse::success(['data' => []], 'Punchout suggestions retrieved successfully');
        }

        // Re-fetch through the tenant + channel scopes: the index is only a lookup.
        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where('status', 'active')
            ->forTenant($session->tenant_id)
            ->forChannel(SalesChannel::PUNCHOUT)
            ->get()
            ->keyBy('id');

        $pricing = $this->resolvePunchoutPriceAction->resolveForProducts(
            $this->tenant($session),
            $session->buyer,
            $products->values(),
        );

        $data = collect($hits)
            ->map(function (array $hit) use ($products, $pricing): ?array {
                $product = $products->get((int) ($hit['id'] ?? 0));

                if ($product === null) {
                    return null;
                }

                $price = $pricing[$product->id] ?? [];

                return [
                    'id' => $product->id,
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'route_key' => $product->slug.'-'.substr((string) $product->uuid, 0, 8),
                    'image_url' => $hit['image_url'] ?? null,
                    'unit_price' => $price['unit_price'] ?? null,
                    'currency' => $price['currency'] ?? null,
                    'unit_of_measure' => $price['unit_of_measure'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return ApiResponse::success(['data' => $data], 'Punchout suggestions retrieved successfully');
    }

    public function show(Request $request): JsonResponse
    {
        $session = $this->session($request);

        // Read from the route rather than declaring `string $key`: Laravel fills
        // scalar controller parameters positionally, so a lone `$key` would
        // receive {tenantIdentifier}, the first route parameter.
        $key = (string) $request->route('key');

        $product = $this->catalogService->show($key, [
            'channel' => SalesChannel::PUNCHOUT,
            'tenant_id' => $session->tenant_id,
        ]);

        $tenant = $this->tenant($session);
        $pricing = $this->resolvePunchoutPriceAction->handle($tenant, $product, null, 1, $session->buyer, forDisplay: true);
        $variants = $product->relationLoaded('variants') ? $product->variants : collect();
        $variantPricing = $this->resolvePunchoutPriceAction->resolveForVariants(
            $tenant,
            $session->buyer,
            $product,
            $variants,
        );

        return ApiResponse::success(
            new PunchoutProductDetailResource($product, $pricing, $variantPricing),
            'Punchout product retrieved successfully',
        );
    }

    public function filters(Request $request): JsonResponse
    {
        $session = $this->session($request);

        return ApiResponse::success(
            $this->catalogService->filters([
                'channel' => SalesChannel::PUNCHOUT,
                'tenant_id' => $session->tenant_id,
            ]),
            'Punchout filters retrieved successfully',
        );
    }

    private function session(Request $request): PunchoutSession
    {
        $session = $request->attributes->get('punchout_session');

        abort_unless($session instanceof PunchoutSession, 500, 'Punchout session was not resolved.');

        return $session;
    }

    private function tenant(PunchoutSession $session): Tenant
    {
        return $session->tenant ?? $session->tenant()->firstOrFail();
    }
}
