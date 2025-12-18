<?php

namespace App\Http\Middleware;

use App\Models\CallCenter\CallCenterUser;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureCallCenterAdmin
{
    /**
     * Restrict access to call center administrators only.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse|RedirectResponse
    {
        $sessionUser = $request->session()->get('user');

        if (!$sessionUser) {
            return redirect()->route('login');
        }

        $ccUser = CallCenterUser::find($sessionUser['id'] ?? null);

        $isCallCenterAdmin = $ccUser && $ccUser->status && $ccUser->admin_prev && ($ccUser->system === 'cc');

        if (!$isCallCenterAdmin) {
            $request->session()->forget('user');

            return redirect()->route('dashboard')->withErrors([
                'auth' => 'You are not authorized to access call center administration.',
            ]);
        }

        $request->session()->put('user.is_admin', (bool) $ccUser->admin_prev);

        return $next($request);
    }
}
