<?php

namespace App\Services\Price;

use App\Actions\Price\PriceCreateAction;
use App\Actions\Price\PriceDeleteAction;
use App\Actions\Price\PriceReadAction;
use App\Actions\Price\PriceSearchByIdAction;
use App\Actions\Price\PriceUpdateAction;
use App\Models\Price;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class PriceService
{
    public function create(array $data): Price
    {
        return (new PriceCreateAction())->handle($data);
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        if (! $user?->hasRole(['super-admin', 'admin'])) {
            $membership = $user?->memberships()->where('is_active', true)->first();
            $filters['tenant_id'] = $membership?->tenant_id;
            $filters['organization_id'] = $membership?->organization_id;
        }

        return (new PriceReadAction())->handle($filters);
    }

    public function searchById(int $id): Price
    {
        return (new PriceSearchByIdAction())->handle($id);
    }

    public function update(Price $price, array $data): Price
    {
        return (new PriceUpdateAction())->handle($price, $data);
    }

    public function delete(Price $price): bool
    {
        return (new PriceDeleteAction())->handle($price);
    }
}
