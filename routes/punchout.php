<?php

use App\Http\Controllers\V1\Punchout\AdminPunchoutBuyerController;
use App\Http\Controllers\V1\Punchout\AdminPunchoutSessionController;
use App\Http\Controllers\V1\Punchout\ManagerPunchoutBuyerController;
use App\Http\Controllers\V1\Punchout\PunchoutCartController;
use App\Http\Controllers\V1\Punchout\PunchoutCatalogController;
use App\Http\Controllers\V1\Punchout\PunchoutController;
use App\Http\Controllers\V1\Punchout\PunchoutCspReportController;
use App\Http\Controllers\V1\Punchout\PunchoutOrderRequestController;
use App\Http\Controllers\V1\Punchout\PunchoutSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Punchout routes
|--------------------------------------------------------------------------
|
| Buyer-facing protocol endpoints are public by design: the buyer's
| procurement system authenticates with cXML/OCI credentials. The UI endpoints
| use an opaque capability token in the Authorization header.
|
*/

Route::prefix('v1/punchout')->group(function (): void {
    Route::post('/{tenantIdentifier}/setup', [PunchoutController::class, 'setup'])
        ->middleware('throttle:punchout-setup')
        ->name('punchout.setup');

    Route::get('/{tenantIdentifier}/start', [PunchoutController::class, 'start'])
        ->middleware('throttle:punchout-start')
        ->name('punchout.start');

    // Backward-compatible cart return. New clients use /cart/return.
    Route::post('/{tenantIdentifier}/cart', [PunchoutController::class, 'cart'])
        ->middleware('throttle:punchout-cart')
        ->name('punchout.cart.legacy');

    // Inbound cXML purchase order. Idempotent by the buyer's payloadID.
    Route::post('/{tenantIdentifier}/order-request', [PunchoutOrderRequestController::class, 'store'])
        ->middleware('throttle:punchout-order')
        ->name('punchout.order-request');

    Route::post('/sessions/exchange', [PunchoutSessionController::class, 'exchange'])
        ->middleware('throttle:punchout-exchange')
        ->name('punchout.sessions.exchange');

    // CSP violation reports arrive from the browser without credentials. The
    // payload is bounded and only reports whose document-uri is the punchout
    // origin are recorded (see PunchoutCspReportController).
    Route::post('/csp-report', [PunchoutCspReportController::class, 'store'])
        ->middleware('throttle:punchout-csp')
        ->name('punchout.csp-report');

    Route::middleware('punchout.session')->group(function (): void {
        Route::get('/sessions/{uuid}', [PunchoutSessionController::class, 'show'])
            ->name('punchout.sessions.show');
        Route::post('/sessions/{uuid}/heartbeat', [PunchoutSessionController::class, 'heartbeat'])
            ->name('punchout.sessions.heartbeat');
        Route::post('/sessions/{uuid}/cancel', [PunchoutSessionController::class, 'cancel'])
            ->name('punchout.sessions.cancel');

        Route::get('/{tenantIdentifier}/catalog/products', [PunchoutCatalogController::class, 'products'])
            ->middleware('throttle:punchout-api')
            ->name('punchout.catalog.products');
        Route::get('/{tenantIdentifier}/catalog/products/{key}', [PunchoutCatalogController::class, 'show'])
            ->middleware('throttle:punchout-api')
            ->name('punchout.catalog.show');
        Route::get('/{tenantIdentifier}/catalog/filters', [PunchoutCatalogController::class, 'filters'])
            ->middleware('throttle:punchout-api')
            ->name('punchout.catalog.filters');

        Route::get('/{tenantIdentifier}/cart', [PunchoutCartController::class, 'index'])
            ->middleware('throttle:punchout-api')
            ->name('punchout.cart.index');
        Route::post('/{tenantIdentifier}/cart/items', [PunchoutCartController::class, 'store'])
            ->middleware('throttle:punchout-cart')
            ->name('punchout.cart.items.store');
        Route::patch('/{tenantIdentifier}/cart/items/{uuid}', [PunchoutCartController::class, 'update'])
            ->middleware('throttle:punchout-cart')
            ->name('punchout.cart.items.update');
        Route::delete('/{tenantIdentifier}/cart/items/{uuid}', [PunchoutCartController::class, 'destroy'])
            ->middleware('throttle:punchout-cart')
            ->name('punchout.cart.items.destroy');
        Route::delete('/{tenantIdentifier}/cart', [PunchoutCartController::class, 'clear'])
            ->middleware('throttle:punchout-cart')
            ->name('punchout.cart.clear');
        Route::post('/{tenantIdentifier}/cart/return', [PunchoutCartController::class, 'returnCart'])
            ->middleware('throttle:punchout-cart')
            ->name('punchout.cart.return');
    });
});

Route::prefix('v1/admin/punchout')
    ->middleware(['auth.api', 'admin'])
    ->group(function (): void {
        Route::get('/sessions', [AdminPunchoutSessionController::class, 'index'])
            ->name('punchout.admin.sessions.index');
        Route::get('/sessions/{uuid}', [AdminPunchoutSessionController::class, 'show'])
            ->name('punchout.admin.sessions.show');

        Route::get('/buyers', [AdminPunchoutBuyerController::class, 'index'])
            ->name('punchout.admin.buyers.index');
        // Registered before /buyers/{uuid} so "options" is not read as a UUID.
        Route::get('/buyers/options', [AdminPunchoutBuyerController::class, 'options'])
            ->name('punchout.admin.buyers.options');
        Route::post('/buyers', [AdminPunchoutBuyerController::class, 'store'])
            ->name('punchout.admin.buyers.store');
        Route::get('/buyers/{uuid}', [AdminPunchoutBuyerController::class, 'show'])
            ->name('punchout.admin.buyers.show');
        Route::put('/buyers/{uuid}', [AdminPunchoutBuyerController::class, 'update'])
            ->name('punchout.admin.buyers.update');
        Route::delete('/buyers/{uuid}', [AdminPunchoutBuyerController::class, 'destroy'])
            ->name('punchout.admin.buyers.destroy');
        Route::post('/buyers/{uuid}/rotate-secret', [AdminPunchoutBuyerController::class, 'rotateSecret'])
            ->name('punchout.admin.buyers.rotate-secret');
    });

/*
 * Tenant-scoped manager API.
 *
 * The tenant is resolved from the caller's active membership by the
 * `punchout.manager` middleware; it is never read from the request. Buyers
 * outside that tenant are reported as 404. It lives under /manage so it cannot
 * be confused with the buyer-facing /v1/punchout endpoints.
 */
Route::prefix('v1/manage/punchout')
    ->middleware(['auth.api', 'punchout.manager'])
    ->group(function (): void {
        Route::get('/buyers', [ManagerPunchoutBuyerController::class, 'index'])
            ->name('punchout.manager.buyers.index');
        // Registered before /buyers/{uuid} so "options" is not read as a UUID.
        Route::get('/buyers/options', [ManagerPunchoutBuyerController::class, 'options'])
            ->name('punchout.manager.buyers.options');
        Route::post('/buyers', [ManagerPunchoutBuyerController::class, 'store'])
            ->name('punchout.manager.buyers.store');
        Route::get('/buyers/{uuid}', [ManagerPunchoutBuyerController::class, 'show'])
            ->name('punchout.manager.buyers.show');
        Route::put('/buyers/{uuid}', [ManagerPunchoutBuyerController::class, 'update'])
            ->name('punchout.manager.buyers.update');
        Route::delete('/buyers/{uuid}', [ManagerPunchoutBuyerController::class, 'destroy'])
            ->name('punchout.manager.buyers.destroy');
        Route::post('/buyers/{uuid}/rotate-secret', [ManagerPunchoutBuyerController::class, 'rotateSecret'])
            ->name('punchout.manager.buyers.rotate-secret');
    });
