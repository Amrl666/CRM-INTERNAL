<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Response;

class AuthenticateWithRoles extends Authenticate
{
    public function handle($request, Closure $next, ...$guards): Response
    {
        if (empty($guards)) {
            $guards = ['web'];
        }

        foreach ($guards as $guard) {
            $this->ensureRole($request, $guard);
        }

        return $next($request);
    }

    protected function ensureRole($request, string $guard): void
    {
        $user = $request->user($guard);

        if (! $user) {
            abort(401);
        }

        $requiredRoles = $this->getRequiredRoles($guard);
        foreach ($requiredRoles as $role) {
            if (! $user->hasRole($role)) {
                abort(403, 'Unauthorized access.');
            }
        }
    }

    protected function getRequiredRoles(string $guard): array
    {
        $guardRoles = [
            'web' => ['user', 'admin', 'sales_manager', 'sales_agent', 'analyst', 'viewer'],
            'auth' => ['user', 'admin', 'sales_manager', 'sales_agent', 'analyst', 'viewer'],
        ];

        return $guardRoles[$guard] ?? [];
    }
}
