<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        $userRoles = $request->user()->getRoleNames()->toArray();

        // Check if user has any of the required roles
        if (!$request->user()->hasAnyRole($roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient role permissions',
                'required_roles' => $roles,
                'user_roles' => $userRoles
            ], 403);
        }

        return $next($request);
    }
}
