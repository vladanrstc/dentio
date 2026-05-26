<?php

namespace App\Http\Middleware;

use App\Models\Patient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $role = $user instanceof Patient ? Patient::ROLE_CLIENT : $user?->role;

        if ($user === null || ! in_array($role, $roles, true)) {
            abort(403, __('errors.unauthorized'));
        }

        return $next($request);
    }
}
