<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!$request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        $userPermissions = $request->user()->getAllPermissions()->pluck('name')->toArray();

        // Check if user has any of the required permissions
        if (!$request->user()->hasAnyPermission($permissions)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient permissions',
                'required_permissions' => $permissions,
                'user_permissions' => $userPermissions
            ], 403);
        }

        return $next($request);
    }
}
