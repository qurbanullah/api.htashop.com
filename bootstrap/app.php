<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LoadUserRoles;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies must be set before any other middleware
        $middleware->use([
            TrustProxies::class,
            HandleCors::class,
        ]);

        // Add API-specific middleware after CORS
        $middleware->api(prepend: [
            ForceHttps::class,
            ForceJsonResponse::class,
        ]);

        // Add middleware to load user roles after authentication
        $middleware->api(append: [
            LoadUserRoles::class,
        ]);

        // Add locale detection middleware to all routes
        $middleware->append(SetLocale::class);

        // Register role and permission middleware
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'auth.api' => ApiAuthenticate::class,
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API routes, even for 404/405 errors
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                $statusCode = $response->getStatusCode();

                // Carry over only the client-facing extras — chiefly the
                // per-field `errors` map of a validation failure, which every
                // form renders next to the offending input. Everything else the
                // framework rendered is deliberately dropped, so debug payloads
                // (`exception`, `file`, `trace`) never reach a client.
                $rendered = $response instanceof JsonResponse
                    ? $response->getData(true)
                    : null;
                $errors = is_array($rendered) ? ($rendered['errors'] ?? null) : null;

                $message = $exception->getMessage();

                // Default messages for common HTTP status codes
                $defaultMessages = [
                    404 => 'The requested resource was not found.',
                    405 => 'The HTTP method is not allowed for this route.',
                    401 => 'Unauthenticated.',
                    403 => 'Forbidden.',
                    500 => 'Internal server error.',
                ];

                // Use default message if exception message is empty
                if (empty($message) && isset($defaultMessages[$statusCode])) {
                    $message = $defaultMessages[$statusCode];
                }

                $payload = [
                    'success' => false,
                    'message' => $message,
                    'status_code' => $statusCode,
                ];

                if (is_array($errors) && $errors !== []) {
                    $payload['errors'] = $errors;
                }

                $json = response()->json($payload, $statusCode);

                // Keep transport-level headers (Retry-After on a throttle, …).
                $json->headers->add($response->headers->all());

                return $json;
            }

            return $response;
        });
    })->create();
