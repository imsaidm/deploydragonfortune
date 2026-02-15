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
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $userRole = $request->user()->role;

        // Hierarchy logic
        $roles = [
            'investor' => 1,
            'creator' => 2,
            'admin' => 3,
            'superAdmin' => 4,
        ];

        $requiredLevel = $roles[$role] ?? 0;
        $userLevel = $roles[$userRole] ?? 0;

        if ($userLevel < $requiredLevel) {
            // Shadow logic: if investor tries to access admin, just send to workspace
            // instead of showing a scary "403 Forbidden" to keep things clean.
            return redirect()->route('workspace')->with('error', 'Unauthorized access.');
        }

        return $next($request);
    }
}
