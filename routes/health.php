<?php

// API Health Check Route for HAProxy
// Add this to your routes/api.php file

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Health Check Endpoint
|--------------------------------------------------------------------------
| HAProxy will use this endpoint to monitor container health
| This endpoint checks database connectivity and basic app status
*/

Route::get('/health', function () {
    try {
        // Check database connection
        $dbStatus = 'OK';
        try {
            DB::connection()->getPdo();
            if (DB::connection()->getDatabaseName()) {
                $dbStatus = 'Connected';
            }
        } catch (\Exception $e) {
            $dbStatus = 'Failed: ' . $e->getMessage();
        }

        // Check disk space
        $diskSpace = disk_free_space('/') / disk_total_space('/') * 100;

        // Check memory usage
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB

        // Container info
        $containerInfo = [
            'container_id' => gethostname(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
        ];

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'uptime' => uptime(),
            'database' => $dbStatus,
            'disk_free_percent' => round($diskSpace, 2),
            'memory_usage_mb' => round($memoryUsage, 2),
            'container' => $containerInfo,
            'load_balancer' => 'haproxy'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'timestamp' => now()->toISOString(),
            'error' => $e->getMessage(),
            'container_id' => gethostname()
        ], 503);
    }
});

// Helper function to get uptime
function uptime() {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        return [
            'load_1min' => $load[0],
            'load_5min' => $load[1],
            'load_15min' => $load[2]
        ];
    }
    return 'unavailable';
}

// Simple health check (minimal overhead)
Route::get('/ping', function () {
    return response()->json(['pong' => true, 'timestamp' => time()], 200);
});

// Detailed status for monitoring
Route::get('/status', function () {
    return response()->json([
        'app' => [
            'name' => config('app.name'),
            'version' => '1.0.0', // Update this with your app version
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'url' => config('app.url'),
        ],
        'php' => [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => extension_loaded('opcache') && opcache_get_status()['opcache_enabled'],
        ],
        'system' => [
            'hostname' => gethostname(),
            'timestamp' => now()->toISOString(),
            'timezone' => date_default_timezone_get(),
        ]
    ], 200);
});
