<?php

namespace Tests\Feature\Punchout;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\BuyerParty;
use App\Models\PunchoutBuyer;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');

    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);
});

function actingBuyerPartyAdmin($test): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $test->actingAs($admin, 'api');

    return $admin;
}

it('gives a new buyer a private party', function () {
    $tenant = punchoutTenant('party-private-tenant');
    $buyer = punchoutBuyer($tenant);

    $buyer->refresh();

    expect($buyer->buyer_party_id)->not->toBeNull();

    $party = $buyer->party;

    expect($party)->not->toBeNull();
    expect($party->metadata['private'] ?? null)->toBeTrue();
    expect($party->metadata['tenant_id'] ?? null)->toBe($tenant->id);
});

it('shares one party between tenants onboarding the same network id', function () {
    $tenantA = punchoutTenant('party-alpha');
    $tenantB = punchoutTenant('party-beta');

    $buyerA = punchoutBuyer($tenantA, ['settings' => ['network_id' => 'AN0123456789']]);
    $buyerB = punchoutBuyer($tenantB, ['settings' => ['network_id' => 'AN0123456789']]);

    expect($buyerA->refresh()->buyer_party_id)->toBe($buyerB->refresh()->buyer_party_id);
    expect(BuyerParty::query()->count())->toBe(1);
    expect(BuyerParty::query()->first()?->network_id)->toBe('AN0123456789');
});

it('lists buyer parties for platform admins', function () {
    actingBuyerPartyAdmin($this);

    $tenant = punchoutTenant('party-list-tenant');
    punchoutBuyer($tenant, ['settings' => ['network_id' => 'AN-LIST-1']]);

    $this->getJson('/api/v1/admin/buyer-parties?q=AN-LIST')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.network_id', 'AN-LIST-1')
        ->assertJsonPath('data.items.0.buyers_count', 1);
});

it('creates and updates a buyer party', function () {
    actingBuyerPartyAdmin($this);

    $create = $this->postJson('/api/v1/admin/buyer-parties', [
        'name' => 'Saudi Aramco',
        'network_id' => 'AN-ARAMCO',
        'country' => 'SA',
    ])->assertCreated();

    $uuid = $create->json('data.uuid');

    $this->putJson('/api/v1/admin/buyer-parties/'.$uuid, [
        'name' => 'Saudi Arabian Oil Company',
        'duns' => '123456789',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Saudi Arabian Oil Company')
        ->assertJsonPath('data.duns', '123456789');
});

it('links buyers with a dry run without writing', function () {
    actingBuyerPartyAdmin($this);

    $tenantA = punchoutTenant('party-dry-alpha');
    $tenantB = punchoutTenant('party-dry-beta');

    $buyerA = punchoutBuyer($tenantA);
    $buyerB = punchoutBuyer($tenantB);
    $sourcePartyId = $buyerB->refresh()->buyer_party_id;

    $response = $this->postJson('/api/v1/admin/buyer-parties/'.$buyerA->party->uuid.'/link', [
        'buyer_uuids' => [$buyerA->uuid, $buyerB->uuid],
        'dry_run' => true,
    ])->assertOk();

    expect($response->json('data.dry_run'))->toBeTrue();
    expect($response->json('data.merged_parties'))->toHaveCount(1);

    expect($buyerB->refresh()->buyer_party_id)->toBe($sourcePartyId);
    expect(BuyerParty::query()->whereKey($sourcePartyId)->exists())->toBeTrue();
});

it('merges colliding buyer items and repoints mappings when linking', function () {
    actingBuyerPartyAdmin($this);

    $tenantA = punchoutTenant('party-merge-alpha');
    $tenantB = punchoutTenant('party-merge-beta');
    $orgA = punchoutOrganization($tenantA, 'org-merge-alpha');
    $orgB = punchoutOrganization($tenantB, 'org-merge-beta');
    $productA = punchoutProduct($tenantA, $orgA, 'Merge Product A', ['punchout']);
    $productB = punchoutProduct($tenantB, $orgB, 'Merge Product B', ['punchout']);

    $buyerA = punchoutBuyer($tenantA);
    $buyerB = punchoutBuyer($tenantB);

    $partyA = $buyerA->party;
    $partyB = $buyerB->refresh()->party;

    $itemA = BuyerItem::query()->create([
        'buyer_party_id' => $partyA->id,
        'code' => '1000048217',
        'short_description' => 'MPU6050 MODULE',
    ]);
    $itemB = BuyerItem::query()->create([
        'buyer_party_id' => $partyB->id,
        'code' => '1000048217',
        'short_description' => 'MPU6050 MODULE',
    ]);

    BuyerItemMapping::query()->create([
        'tenant_id' => $tenantA->id,
        'buyer_party_id' => $partyA->id,
        'buyer_item_id' => $itemA->id,
        'product_id' => $productA->id,
    ]);
    BuyerItemMapping::query()->create([
        'tenant_id' => $tenantB->id,
        'buyer_party_id' => $partyB->id,
        'buyer_item_id' => $itemB->id,
        'product_id' => $productB->id,
    ]);

    $response = $this->postJson('/api/v1/admin/buyer-parties/'.$partyA->uuid.'/link', [
        'buyer_uuids' => [$buyerA->uuid, $buyerB->uuid],
    ])->assertOk();

    expect($response->json('data.items_merged'))->toBe(1);
    expect($response->json('data.mappings_moved'))->toBe(1);

    expect(BuyerParty::query()->whereKey($partyB->id)->exists())->toBeFalse();
    expect(BuyerItem::query()->where('buyer_party_id', $partyA->id)->count())->toBe(1);
    expect(BuyerItem::query()->whereKey($itemB->id)->exists())->toBeFalse();
    expect(BuyerItemMapping::query()
        ->where('tenant_id', $tenantB->id)
        ->where('buyer_item_id', $itemA->id)
        ->exists())->toBeTrue();
    expect($buyerB->refresh()->buyer_party_id)->toBe($partyA->id);
});

it('backfills a party for buyers that do not have one', function () {
    $tenant = punchoutTenant('party-backfill-tenant');
    $buyer = punchoutBuyer($tenant);
    $partyId = $buyer->refresh()->buyer_party_id;

    PunchoutBuyer::query()->whereKey($buyer->id)->update(['buyer_party_id' => null]);
    BuyerParty::query()->whereKey($partyId)->delete();

    $this->artisan('punchout:backfill-buyer-parties')->assertSuccessful();

    expect($buyer->refresh()->buyer_party_id)->not->toBeNull();
    expect($buyer->party)->not->toBeNull();
});
