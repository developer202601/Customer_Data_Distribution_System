<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureRegionalBillingUser
{
    public function handle(Request $request, Closure $next): SymfonyResponse|RedirectResponse
    {
        $sessionUser = $request->session()->get('user');

        if (! $sessionUser) {
            return redirect()->route('login');
        }

        if (($sessionUser['system'] ?? null) !== 'rb') {
            return redirect()->route('dashboard');
        }

        $rbUser = User::find($sessionUser['id'] ?? null);

        if (! $rbUser || ! $rbUser->status) {
            $request->session()->forget('user');

            return redirect()->route('login')->withErrors([
                'auth' => 'Your regional billing account is not active.',
            ]);
        }

        return $next($request);
    }
}
