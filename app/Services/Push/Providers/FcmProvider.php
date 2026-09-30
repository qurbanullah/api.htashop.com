<?php

declare(strict_types=1);

namespace App\Services\Push\Providers;

use App\Enums\PushPlatformEnum;
use App\Services\Push\PushResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Firebase Cloud Messaging, HTTP v1.
 *
 * The legacy server-key API is gone, so a send is two steps: exchange the service
 * account for an OAuth2 access token, then post the message. The token is cached
 * for its lifetime, which is what keeps a fan-out to a hundred devices from minting
 * a hundred tokens.
 */
class FcmProvider
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array<string, mixed>|null */
    private ?array $credentials = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function platform(): PushPlatformEnum
    {
        return PushPlatformEnum::ANDROID;
    }

    public function isConfigured(): bool
    {
        try {
            return $this->projectId() !== null && $this->serviceAccount() !== null;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Sends one message to one device.
     *
     * @param  array<string, mixed>  $message  the FCM `message` object, minus `token`
     */
    public function send(string $token, array $message): PushResult
    {
        $token = trim($token);

        if ($token === '') {
            return PushResult::permanent('empty token');
        }

        try {
            $response = $this->postMessage($token, $message, $this->accessToken());
        } catch (RuntimeException $exception) {
            return PushResult::transient('credentials: '.$exception->getMessage());
        } catch (Throwable $exception) {
            return PushResult::transient('transport: '.$exception->getMessage());
        }

        // An expired access token is worth exactly one retry: the cached one may
        // simply have been minted a moment before a clock skew.
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());

            try {
                $response = $this->postMessage($token, $message, $this->accessToken());
            } catch (Throwable $exception) {
                return PushResult::transient('transport: '.$exception->getMessage());
            }
        }

        if ($response->successful()) {
            return PushResult::delivered();
        }

        $code = $this->errorCode($response->json());

        return PushResult::failed(
            'HTTP '.$response->status().($code !== null ? ' '.$code : ''),
            permanent: $this->isPermanent($response->status(), $code),
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function postMessage(string $token, array $message, string $accessToken): Response
    {
        return Http::withToken($accessToken)
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->asJson()
            ->post(
                'https://fcm.googleapis.com/v1/projects/'.$this->projectId().'/messages:send',
                ['message' => ['token' => $token] + $message],
            );
    }

    /**
     * A cached OAuth2 access token for the service account.
     */
    private function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $serviceAccount = $this->serviceAccount()
            ?? throw new RuntimeException('no service account configured');

        $now = time();

        $assertion = $this->signJwt(
            ['alg' => 'RS256', 'typ' => 'JWT'],
            [
                'iss' => $serviceAccount['client_email'],
                'scope' => self::SCOPE,
                'aud' => $serviceAccount['token_uri'] ?? self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ],
            (string) $serviceAccount['private_key'],
        );

        $response = Http::asForm()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('token exchange failed with HTTP '.$response->status());
        }

        $accessToken = (string) $response->json('access_token');

        if ($accessToken === '') {
            throw new RuntimeException('token exchange returned no access_token');
        }

        // Refreshed a minute early, so a token cannot expire mid-send.
        $expiresIn = (int) $response->json('expires_in', 3600);
        Cache::put($this->tokenCacheKey(), $accessToken, max(60, $expiresIn - 60));

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $header, array $claims, string $privateKey): string
    {
        $segments = $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)).'.'
            .$this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR));

        if (! openssl_sign($segments, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('could not sign the service-account assertion');
        }

        return $segments.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function tokenCacheKey(): string
    {
        return 'push.fcm.access_token.'.$this->projectId();
    }

    private function projectId(): ?string
    {
        $configured = trim((string) ($this->config['project_id'] ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        $projectId = ($this->serviceAccount() ?? [])['project_id'] ?? null;

        return is_string($projectId) && $projectId !== '' ? $projectId : null;
    }

    /**
     * The decoded service-account file, read once per instance.
     *
     * @return array<string, mixed>|null
     */
    private function serviceAccount(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = (string) ($this->config['credentials'] ?? '');

        if ($path === '' || ! is_readable($path)) {
            throw new RuntimeException("service account not readable at {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['private_key'], $decoded['client_email'])) {
            throw new RuntimeException('the service account file is missing private_key or client_email');
        }

        return $this->credentials = $decoded;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function errorCode(?array $body): ?string
    {
        $details = $body['error']['details'] ?? null;

        if (is_array($details)) {
            foreach ($details as $detail) {
                if (is_array($detail) && isset($detail['errorCode'])) {
                    return (string) $detail['errorCode'];
                }
            }
        }

        $status = $body['error']['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * Whether a failure means "this token is dead, stop using it" rather than
     * "try again later". Pruning the wrong one unsubscribes a working device.
     */
    private function isPermanent(int $status, ?string $code): bool
    {
        if (in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT', 'SENDER_ID_MISMATCH'], true)) {
            return true;
        }

        return $status === 404;
    }
}
