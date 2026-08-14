<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to restrict access to admin-only endpoints
 *
 * Only users with 'super-admin' or 'admin' roles can access protected routes
 */
class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if user has admin or super-admin role
        if (!$request->user()->hasAnyRole(['super-admin', 'admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. Admin privileges required.',
                'required_roles' => ['super-admin', 'admin'],
                'user_roles' => $request->user()->getRoleNames()->toArray()
            ], 403);
        }

        return $next($request);
    }
}
