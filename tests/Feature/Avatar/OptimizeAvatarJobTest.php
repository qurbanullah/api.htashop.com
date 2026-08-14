<?php

use App\Jobs\Avatars\OptimizeAvatarJob;
use App\Models\User;
use App\Services\Avatars\AvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

it('calls optimizeAvatarIfNeeded on AvatarService when user is found', function () {
    Log::spy();
    
    $user = User::factory()->create();
    
    $mockService = Mockery::mock(AvatarService::class);
    $mockService->shouldReceive('optimizeAvatarIfNeeded')
        ->once()
        ->with(Mockery::on(function ($arg) use ($user) {
            return $arg instanceof User && $arg->id === $user->id;
        }))
        ->andReturn(true);

    $job = new OptimizeAvatarJob($user->id, 'avatar.jpg');
    $job->handle($mockService);

    Log::shouldHaveReceived('info')->with('Starting avatar optimization job', [
        'user_id' => $user->id,
        'avatar_filename' => 'avatar.jpg',
    ])->once();

    Log::shouldHaveReceived('info')->with('Avatar optimization completed successfully', [
        'user_id' => $user->id,
        'avatar_filename' => 'avatar.jpg',
    ])->once();
});

it('logs a warning and exits early when user is not found', function () {
    Log::spy();
    
    $mockService = Mockery::mock(AvatarService::class);
    $mockService->shouldNotReceive('optimizeAvatarIfNeeded');

    $job = new OptimizeAvatarJob(9999, 'avatar.jpg');
    $job->handle($mockService);

    Log::shouldHaveReceived('warning')->with('User not found for avatar optimization', [
        'user_id' => 9999,
    ])->once();
});

it('releases job back to queue on exception and fails gracefully', function () {
    Log::spy();
    
    $user = User::factory()->create();
    
    $mockService = Mockery::mock(AvatarService::class);
    $mockService->shouldReceive('optimizeAvatarIfNeeded')
        ->once()
        ->andThrow(new Exception('S3 unavailable'));

    // Create a mock of the job to spy on `attempts` and `release` methods
    $job = Mockery::mock(OptimizeAvatarJob::class, [$user->id, 'avatar.jpg'])->makePartial();
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('release')->once()->with(60); // 60 * 1

    $job->handle($mockService);

    Log::shouldHaveReceived('error')->once();
});
