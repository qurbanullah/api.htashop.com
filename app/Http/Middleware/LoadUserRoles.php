<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadUserRoles
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has their roles relationship loaded.
     * This is necessary for authorization policies that depend on roles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$request->user()->relationLoaded('roles')) {
            $request->user()->load('roles');
        }

        return $next($request);
    }
}
