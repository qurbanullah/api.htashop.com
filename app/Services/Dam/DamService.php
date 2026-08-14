<?php

namespace App\Services\Dam;

use App\Actions\Dam\CreateDamAction;
use App\Actions\Dam\ReadDamOwnersAction;
use App\Actions\Dam\ReadDamAssetsAction;
use App\Actions\Dam\SyncDamAssetCollectionsAction;
use App\Models\Dam;
use App\Models\DamCollection;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Variant;
use App\Services\Storage\S3UploadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DamService
{
    public function __construct(
        protected S3UploadService $s3UploadService,
        protected CreateDamAction $createDamAction,
        protected ReadDamOwnersAction $readDamOwnersAction,
        protected ReadDamAssetsAction $readDamAssetsAction,
        protected SyncDamAssetCollectionsAction $syncDamAssetCollectionsAction,
    ) {
    }

    public function readOwners(array $filters = []): Collection
    {
        $user = $this->resolveAuthenticatedUser();

        if (! $this->isPrivilegedUser($user)) {
            $membership = $this->resolveActiveMembership($user);

            if ($membership instanceof Membership) {
                $filters['tenant_id'] = $membership->tenant_id;
                $filters['organization_id'] = $membership->organization_id;
            }
        }

        return $this->readDamOwnersAction->handle($filters);
    }

    public function readAssets(array $filters = []): Collection
    {
        $user = $this->resolveAuthenticatedUser();

        if (! $this->isPrivilegedUser($user)) {
            $membership = $this->resolveActiveMembership($user);

            if ($membership instanceof Membership) {
                $filters['tenant_id'] = $membership->tenant_id;
                $filters['organization_id'] = $membership->organization_id;

                $damableType = data_get($filters, 'damable_type');
                $damableId = data_get($filters, 'damable_id');

                if ($this->isScopedDamOwnerType($damableType)) {
                    if ($damableId === null) {
                        throw ValidationException::withMessages([
                            'damable_id' => ['The damable_id field is required when querying scoped DAM owner types.'],
                        ]);
                    }

                    $this->assertCanAccessOwner((string) $damableType, (int) $damableId, $user);
                }
            }
        }

        return $this->readDamAssetsAction->handle($filters);
    }

    public function generateObjectKey(string $prefix, string $filename): string
    {
        return $this->s3UploadService->generateUniqueKey($prefix, $filename, true);
    }

    public function generatePresignedUploadUrl(
        string $directory,
        string $filename,
        string $contentType,
        int $expiresIn = 3600,
        ?int $maxFileSize = null
    ): array {
        $key = $this->generateObjectKey($directory, $filename);
        $uploadData = $this->s3UploadService->generatePresignedUploadUrl(
            $key,
            $contentType,
            $expiresIn,
            $maxFileSize
        );
        $uploadData['original_filename'] = $filename;

        return $uploadData;
    }

    public function initiateMultipartUpload(
        string $directory,
        string $filename,
        string $contentType,
        int $fileSize,
        int $chunkSize
    ): array {
        $uploadData = $this->s3UploadService->initiateMultipartUpload(
            $this->generateObjectKey($directory, $filename),
            $contentType
        );

        return [
            'upload_id' => $uploadData['upload_id'],
            'key' => $uploadData['key'],
            'bucket' => $uploadData['bucket'],
            'total_parts' => (int) ceil($fileSize / $chunkSize),
            'chunk_size' => $chunkSize,
            'file_size' => $fileSize,
            'original_filename' => $filename,
        ];
    }

    public function getMultipartUploadUrls(
        string $key,
        string $uploadId,
        array $partNumbers,
        int $expiresIn = 3600
    ): array {
        $urls = [];

        foreach ($partNumbers as $partNumber) {
            $urls[] = [
                'part_number' => $partNumber,
                'url' => $this->s3UploadService->generateMultipartUploadUrl(
                    $key,
                    $uploadId,
                    $partNumber,
                    $expiresIn
                ),
            ];
        }

        return ['urls' => $urls];
    }

    public function completeMultipartUpload(string $key, string $uploadId, array $parts): array
    {
        $result = $this->s3UploadService->completeMultipartUpload($key, $uploadId, $parts);

        return [
            'location' => $result['location'],
            'key' => $result['key'],
            'bucket' => $result['bucket'],
            'metadata' => $this->s3UploadService->getFileMetadata($key),
        ];
    }

    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        $this->s3UploadService->abortMultipartUpload($key, $uploadId);
    }

    public function verifyUpload(string $key): array
    {
        $user = $this->resolveAuthenticatedUser();
        $this->assertCanAccessObjectKey($key, $user);

        $exists = $this->s3UploadService->fileExists($key, maxRetries: 2, delayMs: 100);

        if (! $exists) {
            return [
                'exists' => false,
                'key' => $key,
                'metadata' => null,
            ];
        }

        return [
            'exists' => true,
            'key' => $key,
            'metadata' => $this->s3UploadService->getFileMetadata($key, maxRetries: 2, delayMs: 100),
        ];
    }

    public function generateAuthenticatedAccessUrl(string $key, int $expiresIn = 900, ?string $filename = null): ?array
    {
        $user = $this->resolveAuthenticatedUser();
        $this->assertCanAccessObjectKey($key, $user);

        if (! $this->s3UploadService->fileExists($key)) {
            return null;
        }

        return [
            'url' => $this->s3UploadService->generatePresignedDownloadUrl($key, $expiresIn, $filename),
            'expires_in' => $expiresIn,
            'key' => $key,
        ];
    }

    public function generateAccessUrl(string $key, int $expiresIn = 900, ?string $filename = null): ?array
    {
        if (! $this->s3UploadService->fileExists($key)) {
            return null;
        }

        return [
            'url' => $this->s3UploadService->generatePresignedDownloadUrl($key, $expiresIn, $filename),
            'expires_in' => $expiresIn,
            'key' => $key,
        ];
    }

    protected function resolveDamByKey(string $key): ?Dam
    {
        return Dam::query()->where('object_key', $key)->first();
    }

    protected function assertCanAccessObjectKey(string $key, User $user): ?Dam
    {
        $dam = $this->resolveDamByKey($key);

        if (! $dam || $this->isPrivilegedUser($user)) {
            return $dam;
        }

        if ($dam->damable_type && $dam->damable_id !== null && $this->isScopedDamOwnerType($dam->damable_type)) {
            $this->assertCanAccessOwner($dam->damable_type, $dam->damable_id, $user);
        }

        return $dam;
    }

    public function ingest(array $data): Dam
    {
        $user = Auth::user();
        $damableType = data_get($data, 'damable_type');
        $damableId = data_get($data, 'damable_id');

        if ($this->isScopedDamOwnerType($damableType) && $damableId !== null && $user instanceof User) {
            $this->assertCanAccessOwner((string) $damableType, (int) $damableId, $user);
        }

        $objectKey = data_get($data, 'object_key');

        if ($objectKey) {
            $metadata = $this->s3UploadService->getFileMetadata($objectKey, maxRetries: 2, delayMs: 100);

            if ($metadata !== null) {
                $data['mime_type'] ??= $metadata['content_type'] ?? null;
                $data['size'] ??= $metadata['size'] ?? null;
                $data['etag'] ??= $metadata['etag'] ?? null;
                $data['metadata'] ??= $metadata;
            }
        }

        return DB::transaction(function () use ($data): Dam {
            $dam = $this->createDamAction->handle($data);

            $collections = $this->resolveCollectionsForAsset(
                data_get($data, 'collection_name'),
                data_get($data, 'collection_keys', [])
            );

            $this->syncDamAssetCollectionsAction->handle($dam, $collections);

            return $dam->load('collections');
        });
    }

    /**
     * @param  array<int, string>  $requestedKeys
     * @return Collection<int, DamCollection>
     */
    protected function resolveCollectionsForAsset(?string $assetTypeKey, array $requestedKeys): Collection
    {
        $keys = collect($requestedKeys)
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values();

        if (is_string($assetTypeKey) && $assetTypeKey !== '') {
            DamCollection::query()->firstOrCreate(
                ['key' => $assetTypeKey],
                [
                    'name' => Str::headline(str_replace(['_', '-'], ' ', $assetTypeKey)),
                    'kind' => 'asset_type',
                    'is_active' => true,
                    'is_system' => true,
                ]
            );

            $keys->prepend($assetTypeKey);
        }

        $keys = $keys->unique()->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        $collections = DamCollection::query()
            ->whereIn('key', $keys)
            ->where('is_active', true)
            ->get();

        $missingKeys = $keys->diff($collections->pluck('key'));

        if ($missingKeys->isNotEmpty()) {
            throw ValidationException::withMessages([
                'collection_keys' => [
                    'Unknown DAM collections: ' . $missingKeys->implode(', '),
                ],
            ]);
        }

        return $collections->sortBy([
            ['kind', 'asc'],
            ['name', 'asc'],
        ])->values();
    }

    protected function resolveAuthenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated DAM access is not allowed.');
        }

        return $user;
    }

    protected function resolveActiveMembership(User $user): ?Membership
    {
        return $user->memberships()->where('is_active', true)->first();
    }

    protected function isPrivilegedUser(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    protected function isScopedDamOwnerType(mixed $damableType): bool
    {
        return in_array($damableType, [
            Product::class,
            Variant::class,
            Organization::class,
            Tenant::class,
        ], true);
    }

    protected function assertCanAccessOwner(string $damableType, int $damableId, User $user): Model
    {
        $membership = $this->isPrivilegedUser($user)
            ? null
            : $this->resolveActiveMembership($user);

        /** @var Model|null $owner */
        $owner = $damableType::query()->find($damableId);

        if (! $owner instanceof Model) {
            throw ValidationException::withMessages([
                'damable_id' => ['The selected DAM owner could not be found.'],
            ]);
        }

        if ($membership === null) {
            return $owner;
        }

        if ($owner instanceof Tenant) {
            if ($membership->tenant_id !== $owner->getKey()) {
                throw new AuthorizationException('You are not allowed to access DAM resources for this tenant.');
            }

            return $owner;
        }

        if ($owner instanceof Organization) {
            if ($membership->tenant_id !== $owner->tenant_id || $membership->organization_id !== $owner->getKey()) {
                throw new AuthorizationException('You are not allowed to access DAM resources for this organization.');
            }

            return $owner;
        }

        if ($owner instanceof Product) {
            if ($membership->tenant_id !== $owner->tenant_id || $membership->organization_id !== $owner->organization_id) {
                throw new AuthorizationException('You are not allowed to access DAM resources for this product.');
            }

            return $owner;
        }

        if ($owner instanceof Variant) {
            $owner->loadMissing('product');
            $product = $owner->product;

            if (! $product instanceof Product) {
                throw ValidationException::withMessages([
                    'damable_id' => ['The selected variant is missing its product owner.'],
                ]);
            }

            if ($membership->tenant_id !== $product->tenant_id || $membership->organization_id !== $product->organization_id) {
                throw new AuthorizationException('You are not allowed to access DAM resources for this variant.');
            }

            return $owner;
        }

        return $owner;
    }
}
