<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Signs out sessions started before the user's sessions_valid_from (PRD F-04). */
class ValidSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $request->hasSession()) {
            $at = $request->session()->get('auth_at');
            if ($at === null) {
                $request->session()->put('auth_at', now()->timestamp);
            } elseif ($user->sessions_valid_from && $at < $user->sessions_valid_from->timestamp) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login')->with('status', 'Your access changed. Sign in again.');
            }
        }

        return $next($request);
    }
}
