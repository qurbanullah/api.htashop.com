<?php

namespace App\Http\Responses\V1;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, mixed $data = null, int $status = 400): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($data)) {
            // The clients read extras straight off the envelope — the per-field
            // `errors` map, `requires_email_verification`, `email`, … — so lift
            // an array payload to the top level while still exposing `data` for
            // callers that already read it from there. The envelope keys win.
            if (is_array($data)) {
                $payload = array_merge($data, $payload);
            }

            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }
}
