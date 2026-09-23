<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\KnowledgeEntry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);
    Cache::flush();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists knowledge entries with a pagination envelope', function () {
    KnowledgeEntry::create([
        'title' => 'Shipping policy',
        'body' => 'We ship to many countries.',
        'status' => 'published',
        'locale' => '*',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/knowledge-entries')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.data.0.title', 'Shipping policy')
        ->assertJsonPath('data.data.0.status', 'published');
});

it('filters knowledge entries by status and search term', function () {
    KnowledgeEntry::create(['title' => 'Draft entry', 'body' => 'body', 'status' => 'draft', 'locale' => '*']);
    KnowledgeEntry::create(['title' => 'Returns policy', 'body' => 'returns body', 'status' => 'published', 'locale' => '*']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/knowledge-entries?status=published&search=Returns')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.data.0.title', 'Returns policy');
});

it('creates a knowledge entry and publishes it', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/knowledge-entries', [
            'title' => 'Warranty information',
            'body' => 'Warranty terms are described in the warranty policy.',
            'question' => 'What is the warranty?',
            'locale' => 'en',
            'tags' => ['warranty'],
            'status' => 'published',
            'priority' => 5,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Warranty information')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.created_by', $this->admin->id);

    $entry = KnowledgeEntry::query()->where('title', 'Warranty information')->firstOrFail();

    expect($entry->published_at)->not->toBeNull()
        ->and($entry->tags)->toBe(['warranty']);
});

it('validates required fields on create', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/knowledge-entries', ['body' => 'missing a title'])
        ->assertStatus(422);
});

it('updates and unpublishes a knowledge entry', function () {
    $entry = KnowledgeEntry::create([
        'title' => 'Old title',
        'body' => 'old body',
        'status' => 'published',
        'locale' => '*',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/admin/knowledge-entries/{$entry->uuid}", ['title' => 'New title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New title');

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/knowledge-entries/{$entry->uuid}/actions/unpublish")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});

it('publishes a draft entry', function () {
    $entry = KnowledgeEntry::create([
        'title' => 'Draft',
        'body' => 'body',
        'status' => 'draft',
        'locale' => '*',
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/knowledge-entries/{$entry->uuid}/actions/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($entry->fresh()->published_at)->not->toBeNull();
});

it('deletes a knowledge entry', function () {
    $entry = KnowledgeEntry::create([
        'title' => 'Disposable',
        'body' => 'body',
        'status' => 'draft',
        'locale' => '*',
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/knowledge-entries/{$entry->uuid}")
        ->assertOk();

    expect(KnowledgeEntry::query()->where('uuid', $entry->uuid)->exists())->toBeFalse();
});

it('returns 404 for an unknown knowledge entry', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/knowledge-entries/00000000-0000-0000-0000-000000000000')
        ->assertStatus(404);
});

it('returns knowledge base statistics', function () {
    KnowledgeEntry::create(['title' => 'A', 'body' => 'b', 'status' => 'published', 'locale' => '*']);
    KnowledgeEntry::create(['title' => 'B', 'body' => 'b', 'status' => 'draft', 'locale' => '*']);
    KnowledgeEntry::create(['title' => 'C', 'body' => 'b', 'status' => 'draft', 'locale' => '*']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/knowledge-entries/statistics')
        ->assertOk()
        ->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.published', 1)
        ->assertJsonPath('data.draft', 2)
        ->assertJsonPath('data.archived', 0);
});

it('forbids non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/admin/knowledge-entries')
        ->assertStatus(403);
});
