<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsSet
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->password) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), [
            'password.setup.edit',
            'password.setup.update',
            'logout',
        ], true)) {
            return $next($request);
        }

        return redirect()->route('password.setup.edit');
    }
}
