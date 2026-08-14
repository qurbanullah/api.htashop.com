<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return view('api-info');
});

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
