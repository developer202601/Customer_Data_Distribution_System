<?php

namespace App\Http\Middleware;

use App\Models\CallCenter\CallCenterUser;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureCallCenterUser
{
    /**
     * Guarantee the current session belongs to a call center user.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse|RedirectResponse
    {
        $sessionUser = $request->session()->get('user');

        if (!$sessionUser) {
            return redirect()->route('login');
        }

        if (($sessionUser['system'] ?? null) !== 'cc') {
            return redirect()->route('dashboard');
        }

        $ccUser = CallCenterUser::find($sessionUser['id'] ?? null);

        if (!$ccUser || !$ccUser->status) {
            $request->session()->forget('user');

            return redirect()->route('login')->withErrors([
                'auth' => 'Your call center account is not active.',
            ]);
        }

        return $next($request);
    }
}
