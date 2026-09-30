<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Device\DestroyDeviceTokenRequest;
use App\Http\Requests\V1\Device\StoreDeviceTokenRequest;
use App\Http\Responses\V1\ApiResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Push destinations for the native shells.
 *
 * The app registers the FCM/APNs token it received once a user is signed in,
 * and detaches it on sign-out so a device stops receiving the previous account's
 * order updates.
 */
class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user('api');
        $data = $request->validated();

        // The same push token migrates between accounts when a device signs out
        // and back in, so match on the token and reassign ownership.
        $deviceToken = DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'],
                'device_id' => $data['device_id'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_used_at' => now(),
            ]
        );

        // A device that re-registered with a fresh push token leaves its former
        // row behind; drop it so the provider never targets a retired token.
        if (! empty($data['device_id'])) {
            DeviceToken::query()
                ->where('user_id', $user->id)
                ->where('device_id', $data['device_id'])
                ->whereKeyNot($deviceToken->getKey())
                ->delete();
        }

        return ApiResponse::success([
            'uuid' => $deviceToken->uuid,
            'platform' => $deviceToken->platform,
        ], 'Device registered for push notifications.', 201);
    }

    public function destroy(DestroyDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user('api');

        // Scoped to the caller: a token belonging to another account deletes
        // nothing rather than erroring, so sign-out is always a safe no-op.
        $deleted = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $request->validated()['token'])
            ->delete();

        Log::info('Push device token detached', [
            'user_id' => $user->id,
            'deleted' => $deleted,
        ]);

        return ApiResponse::success(['deleted' => $deleted > 0], 'Device unregistered.');
    }
}
