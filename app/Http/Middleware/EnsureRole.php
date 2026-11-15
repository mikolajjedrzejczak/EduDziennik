<?php

namespace App\Http\Middleware;

use App\Services\Authorization\RecordAccess;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = $request->user();
        if (! $actor) {
            throw new AuthenticationException;
        }
        abort_unless(RecordAccess::active($actor) && in_array($actor->role, $roles, true), 403);

        return $next($request);
    }
}
