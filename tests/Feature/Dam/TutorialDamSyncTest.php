<?php

use App\Http\Resources\V1\Tutorial\TutorialResource;
use App\Models\Dam;
use App\Models\Tutorial;
use App\Models\User;
use App\Services\Storage\S3UploadService;
use App\Services\Tutorial\TutorialService;
use Illuminate\Http\Request;
use Mockery\MockInterface;

it('mirrors tutorial thumbnail and video file keys into dam collections on create', function () {
    $user = User::factory()->create();

    app()->instance(S3UploadService::class, Mockery::mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getFileMetadata')
            ->twice()
            ->andReturnUsing(function (string $objectKey, int $maxRetries = 2, int $delayMs = 100) {
                if ($objectKey === 'images/tutorials/thumbnail.png') {
                    return [
                        'content_type' => 'image/png',
                        'size' => 2048,
                        'etag' => 'etag-thumbnail',
                    ];
                }

                return [
                    'content_type' => 'video/mp4',
                    'size' => 409600,
                    'etag' => 'etag-video',
                ];
            });
    }));

    $tutorial = app(TutorialService::class)->createTutorial([
        'title' => 'How to inspect DAM assets',
        'excerpt' => 'Tutorial excerpt',
        'content' => 'Tutorial content',
        'status' => 'published',
        'thumbnail' => 'images/tutorials/thumbnail.png',
        'video_file' => 'videos/tutorials/tutorial.mp4',
    ], $user->id);

    expect($tutorial->thumbnail)->toBe('images/tutorials/thumbnail.png');
    expect($tutorial->relationLoaded('dams'))->toBeTrue();
    expect($tutorial->dams->where('collection_name', 'thumbnail')->count())->toBe(1);
    expect($tutorial->dams->where('collection_name', 'video_file')->count())->toBe(1);

    $thumbnailAsset = $tutorial->dams->firstWhere('collection_name', 'thumbnail');
    $videoAsset = $tutorial->dams->firstWhere('collection_name', 'video_file');

    expect($thumbnailAsset?->object_key)->toBe('images/tutorials/thumbnail.png');
    expect($thumbnailAsset?->mime_type)->toBe('image/png');
    expect($videoAsset?->object_key)->toBe('videos/tutorials/tutorial.mp4');
    expect($videoAsset?->mime_type)->toBe('video/mp4');
});

it('marks previous tutorial dam assets as non current when a replacement thumbnail is saved', function () {
    $user = User::factory()->create();

    $tutorial = Tutorial::create([
        'title' => 'Legacy tutorial',
        'slug' => 'legacy-tutorial',
        'type' => 'video',
        'status' => 'draft',
        'thumbnail' => 'images/tutorials/old-thumbnail.png',
        'created_by' => $user->id,
    ]);

    $oldAsset = Dam::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'damable_type' => Tutorial::class,
        'damable_id' => $tutorial->id,
        'collection_name' => 'thumbnail',
        'file_name' => 'old-thumbnail.png',
        'disk' => config('dam.default_disk'),
        'bucket' => config('dam.default_bucket'),
        'object_key' => 'images/tutorials/old-thumbnail.png',
        'is_current' => true,
    ]);

    app()->instance(S3UploadService::class, Mockery::mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getFileMetadata')
            ->once()
            ->with('images/tutorials/new-thumbnail.png', 2, 100)
            ->andReturn([
                'content_type' => 'image/png',
                'size' => 3072,
                'etag' => 'etag-new-thumbnail',
            ]);
    }));

    $updatedTutorial = app(TutorialService::class)->updateTutorial($tutorial, [
        'thumbnail' => 'images/tutorials/new-thumbnail.png',
    ]);

    $oldAsset->refresh();
    $newAsset = $updatedTutorial->dams->firstWhere('object_key', 'images/tutorials/new-thumbnail.png');

    expect($updatedTutorial->thumbnail)->toBe('images/tutorials/new-thumbnail.png');
    expect($oldAsset->is_current)->toBeFalse();
    expect($newAsset)->not->toBeNull();
    expect($newAsset?->is_current)->toBeTrue();
});

it('serializes additive tutorial dam assets while preserving legacy fields', function () {
    $user = User::factory()->create();

    $tutorial = Tutorial::create([
        'title' => 'Tutorial resource check',
        'slug' => 'tutorial-resource-check',
        'type' => 'video',
        'status' => 'published',
        'thumbnail' => 'images/tutorials/legacy-thumbnail.png',
        'video_file' => 'videos/tutorials/legacy-video.mp4',
        'created_by' => $user->id,
    ]);

    $thumbnailAsset = new Dam([
        'id' => 1,
        'uuid' => 'dam-thumbnail-uuid',
        'collection_name' => 'thumbnail',
        'file_name' => 'thumbnail.png',
        'object_key' => 'images/tutorials/current-thumbnail.png',
        'mime_type' => 'image/png',
        'size' => 5000,
        'is_current' => true,
    ]);

    $videoAsset = new Dam([
        'id' => 2,
        'uuid' => 'dam-video-uuid',
        'collection_name' => 'video_file',
        'file_name' => 'tutorial.mp4',
        'object_key' => 'videos/tutorials/current-video.mp4',
        'mime_type' => 'video/mp4',
        'size' => 9000,
        'is_current' => true,
    ]);

    $tutorial->setRelation('dams', collect([$thumbnailAsset, $videoAsset]));

    $resource = new TutorialResource($tutorial);
    $payload = $resource->toArray(new Request());

    expect($payload['thumbnail'])->toBe('images/tutorials/legacy-thumbnail.png');
    expect($payload['video_file'])->toBe('videos/tutorials/legacy-video.mp4');
    expect($payload['assets']['thumbnail']['object_key'])->toBe('images/tutorials/current-thumbnail.png');
    expect($payload['assets']['video_file']['object_key'])->toBe('videos/tutorials/current-video.mp4');
});
