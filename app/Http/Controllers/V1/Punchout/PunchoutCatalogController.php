<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Actions\Punchout\ResolvePunchoutPriceAction;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\PunchoutCatalogIndexRequest;
use App\Http\Resources\V1\Punchout\PunchoutProductDetailResource;
use App\Http\Resources\V1\Punchout\PunchoutProductResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\PunchoutSession;
use App\Models\Tenant;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PunchoutCatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService,
        protected ResolvePunchoutPriceAction $resolvePunchoutPriceAction,
    ) {}

    public function products(PunchoutCatalogIndexRequest $request): JsonResponse
    {
        $session = $this->session($request);

        $filters = $request->validated();
        $filters['channel'] = SalesChannel::PUNCHOUT;
        $filters['tenant_id'] = $session->tenant_id;

        $products = $this->catalogService->products($filters);
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
