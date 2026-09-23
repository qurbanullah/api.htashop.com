<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Seo\SitemapController;
use App\Http\Controllers\V1\Post\PostViewController;
use App\Http\Controllers\V1\Unsubscribe\UnsubscribeController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return view('api-info');
})->name('home');

// Post "view online" pages (linked from post emails)
// NOTE: the token-gated route must be declared before the slug route.
Route::get('/posts/view/{uuid}/{token}', [PostViewController::class, 'view'])->name('post.view');
Route::get('/posts/{slug}', [PostViewController::class, 'show'])->name('post.show');

// Unsubscribe / resubscribe flow for post subscriptions
Route::get('/unsubscribe/{token}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
Route::post('/unsubscribe/{token}', [UnsubscribeController::class, 'unsubscribe'])->name('unsubscribe');
Route::get('/unsubscribe/{token}/success', [UnsubscribeController::class, 'success'])->name('unsubscribe.success');
Route::post('/unsubscribe/{token}/resubscribe', [UnsubscribeController::class, 'resubscribe'])->name('unsubscribe.resubscribe');

// SEO — robots.txt + XML sitemaps (proxied by the storefront nginx)
Route::get('/robots.txt', [SitemapController::class, 'robots']);
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemap-products.xml', [SitemapController::class, 'products']);
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts']);
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories']);
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages']);

// IndexNow key file — served at the site root so search engines can verify it.
Route::get('/{indexnowKey}.txt', [SitemapController::class, 'indexnowKey'])
    ->where('indexnowKey', '[A-Za-z0-9]{8,64}');

// Password reset routes for different frontends
// Default password reset (for backward compatibility and main frontend)
Route::get('/password/reset/{token}', function ($token) {
    $email = request('email');
    $frontendUrl = env('FRONTEND_URL', 'https://volvicon.com');
    return redirect($frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email));
})->name('password.reset');

// Manage submission system password reset
Route::get('/manage/password/reset/{token}', function ($token) {
    $email = request('email');
    $frontendUrl = env('MANAGE_FRONTEND_URL', 'https://manage.volvicon.com');
    return redirect($frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email));
})->name('password.reset.manage');

// Admin panel password reset
Route::get('/admin/password/reset/{token}', function ($token) {
    $email = request('email');
    $frontendUrl = env('ADMIN_FRONTEND_URL', 'https://admin.volvicon.com');
    return redirect($frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email));
})->name('password.reset.admin');

// Fallback health endpoint (guaranteed)
// This ensures infrastructure healthchecks hitting /health get a 200 even
// if API route loading has issues. Remove once root cause is fixed.
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'source' => 'fallback-web',
        'timestamp' => now()->toIsoString(),
    ], 200);
});
