<?php

namespace App\Http\Controllers\V1\Address;

use App\Actions\Address\AddressCreateAction;
use App\Actions\Address\AddressDeleteAction;
use App\Actions\Address\AddressUpdateAction;
use App\Actions\Address\AddressSetPrimaryAction;
use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Address;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct(
        protected AddressCreateAction $createAction,
        protected AddressUpdateAction $updateAction,
        protected AddressDeleteAction $deleteAction,
        protected AddressSetPrimaryAction $setPrimaryAction,
    ) {
    }

    /**
     * List addresses for the authenticated user (or their organization).
     */
    public function index(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);

        $addresses = $owner->addresses()
            ->with('country')
            ->orderByDesc('is_primary')
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::success(
            \App\Http\Resources\V1\Address\AddressResource::collection($addresses),
            'Addresses retrieved successfully',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);
        $data = $this->validated($request, $owner);

        $address = $this->createAction->handle($owner, $data);

        return ApiResponse::success(
            new \App\Http\Resources\V1\Address\AddressResource($address->load('country')),
            'Address created successfully',
            201,
        );
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $address = Address::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorizeAddress($address);

        $address = $this->updateAction->handle($address, $this->validated($request, $address->addressable, true));

        return ApiResponse::success(
            new \App\Http\Resources\V1\Address\AddressResource($address->load('country')),
            'Address updated successfully',
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $address = Address::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorizeAddress($address);

        $this->deleteAction->handle($address);

        return ApiResponse::success(null, 'Address deleted successfully');
    }

    public function setPrimary(string $uuid): JsonResponse
    {
        $address = Address::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorizeAddress($address);

        $this->setPrimaryAction->handle($address);

        return ApiResponse::success(
            new \App\Http\Resources\V1\Address\AddressResource($address->fresh('country')),
            'Primary address updated successfully',
        );
    }

    /**
     * Resolve the address owner from the request. Defaults to the authenticated
     * user; an organization can be targeted with ?addressable_type=organization.
     */
    private function resolveOwner(Request $request): Model
    {
        $user = $request->user();

        if ($request->string('addressable_type')->toString() === 'organization') {
            $membership = $user?->memberships()->where('is_active', true)->first();
            $organization = $membership?->organization;
            abort_if(! $organization, 404, 'Organization not found');

            return $organization;
        }

        return $user;
    }

    private function validated(Request $request, Model $owner, bool $partial = false): array
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:shipping,billing,warehouse,vendor,other'],
            'label' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'state' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if (! $partial && $owner instanceof User && empty($data['type'])) {
            $data['type'] = 'shipping';
        }

        return $data;
    }

    private function authorizeAddress(Address $address): void
    {
        $user = auth()->user();
        $owner = $address->addressable;

        if ($owner instanceof User) {
            abort_unless($owner->is($user), 403, 'You do not own this address.');
        }

        if ($owner instanceof Organization) {
            $membership = $user?->memberships()->where('is_active', true)->where('organization_id', $owner->id)->exists();
            abort_unless($membership, 403, 'You do not have access to this address.');
        }
    }
}
