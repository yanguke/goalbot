<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects /admin routes. Grants access when the request is from an
 * authenticated user OR carries a valid legacy admin key (?key=...), so
 * existing bookmarks and automation keep working alongside session login.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $expected = config('app.admin_key', env('ADMIN_KEY'));
        if ($expected && $request->query('key') === $expected) {
            return $next($request);
        }

        // Store the intended URL and send them to the login screen.
        return redirect()->guest(route('admin.login'));
    }
}
