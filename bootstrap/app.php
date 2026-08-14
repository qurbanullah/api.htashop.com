<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            \App\Http\Middleware\TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Add API-specific middleware after CORS
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceHttps::class,
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Add middleware to load user roles after authentication
        $middleware->api(append: [
            \App\Http\Middleware\LoadUserRoles::class,
        ]);

        // Add locale detection middleware to all routes
        $middleware->append(\App\Http\Middleware\SetLocale::class);

        // Register role and permission middleware
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'auth.api' => \App\Http\Middleware\ApiAuthenticate::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API routes, even for 404/405 errors
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $statusCode = $response->getStatusCode();
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

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'status_code' => $statusCode,
                ], $statusCode);
            }

            return $response;
        });
    })->create();
