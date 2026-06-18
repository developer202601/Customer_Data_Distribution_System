<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use League\OAuth2\Client\Provider\GenericProvider;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private function normalizeAssignment(?string $assignment): string
    {
        return strtolower(trim((string) $assignment));
    }

    private function provider()
    {
        return new GenericProvider([
            'clientId'                => env('MICROSOFT_CLIENT_ID'),
            'clientSecret'            => env('MICROSOFT_CLIENT_SECRET'),
            'redirectUri'             => env('MICROSOFT_REDIRECT_URI'),
            'urlAuthorize'            => 'https://login.microsoftonline.com/' . env('MICROSOFT_TENANT_ID') . '/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/' . env('MICROSOFT_TENANT_ID') . '/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
        ]);
    }

    public function microsoftRedirect()
    {
        $provider = $this->provider();

        $authUrl = $provider->getAuthorizationUrl([
            'scope' => 'openid profile email User.Read'
        ]);

        session(['oauth_state' => $provider->getState()]);

        return redirect($authUrl);
    }

    public function microsoftCallback()
    {
        $provider = $this->provider();

        $token = $provider->getAccessToken('authorization_code', [
            'code' => request('code')
        ]);

        $graphUrl = 'https://graph.microsoft.com/v1.0/me';

        $response = file_get_contents(
            $graphUrl,
            false,
            stream_context_create([
                'http' => [
                    'header' => 'Authorization: Bearer ' . $token->getToken()
                ]
            ])
        );

        $userData = json_decode($response, true);
        print_r($userData);
        exit;
        // $user = User::updateOrCreate(
        //     ['email' => $userData['mail'] ?? $userData['userPrincipalName']],
        //     [
        //         'name' => $userData['displayName']
        //     ]
        // );

        // Auth::login($user);

        // return redirect('/dashboard');
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('user')) {
            $sessionUser = $request->session()->get('user');

            $targetRoute = match ($sessionUser['system'] ?? null) {
                'cc' => $this->getCCLoginRedirect($sessionUser),
                'rb' => $this->getRBLoginRedirect($sessionUser),
                default => 'dashboard',
            };

            return redirect()->route($targetRoute);
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'digits:6'],
        ]);

        $user = User::where('username', $validated['username'])->first();

        if (!$user) {
            return back()
                ->withErrors(['username' => 'Invalid username.'])
                ->withInput();
        }

        if (!$user->status) {
            return back()
                ->withErrors(['username' => 'This user is disabled.'])
                ->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('user', [
            'id' => $user->id,
            'username' => $user->username,
            'is_admin' => (bool) $user->admin_prev,
            'system' => $user->system,
            'assignment' => $user->assignment ?? null,
            'name' => $user->name ?? null,
        ]);

        $targetRoute = match ($user->system) {
            'cc' => $this->getCCLoginRedirect([
                'is_admin' => (bool) $user->admin_prev,
                'assignment' => $user->assignment ?? null,
            ]),
            'rb' => $this->getRBLoginRedirect([
                'is_admin' => (bool) $user->admin_prev,
                'assignment' => $user->assignment ?? null,
            ]),
            default => 'dashboard',
        };

        return redirect()->route($targetRoute);
    }

    private function getCCLoginRedirect(array $user): string
    {
        $assignment = $this->normalizeAssignment($user['assignment'] ?? null);
        $isAdmin = $user['is_admin'] ?? false;

        // Super admins go to overview
        if ($assignment === 'super') {
            return 'cc.dashboard';
        }

        // Regional admins go to region dashboard
        if ($assignment !== '' && !str_starts_with($assignment, 'supervisor_') && !str_starts_with($assignment, 'rtom_') && !str_starts_with($assignment, 'caller_')) {
            return 'cc.region.dashboard';
        }

        // RTOM admins go to RTOM assignment management
        if (str_starts_with($assignment, 'rtom_')) {
            return 'cc.region.assign.index';
        }

        // Supervisors go to supervisor dashboard
        if (str_starts_with($assignment, 'supervisor_')) {
            return 'cc.supervisor.dashboard';
        }

        // Regular admins go to user management
        if ($isAdmin) {
            return 'cc.users.index';
        }

        // Regular callers go to assignments
        return 'cc.assignments.manage';
    }

    private function getRBLoginRedirect(array $user): string
    {
        $assignment = $this->normalizeAssignment($user['assignment'] ?? null);
        $isAdmin = $user['is_admin'] ?? false;

        if ($assignment === 'super') {
            return 'rb.dashboard';
        }

        if ($assignment !== '' && !str_starts_with($assignment, 'supervisor_') && !str_starts_with($assignment, 'rtom_') && !str_starts_with($assignment, 'caller_')) {
            return 'rb.region.dashboard';
        }

        if (str_starts_with($assignment, 'rtom_')) {
            return 'rb.rtom.dashboard';
        }

        if (str_starts_with($assignment, 'supervisor_')) {
            return 'rb.supervisor.dashboard';
        }

        if ($isAdmin) {
            return 'rb.users.index';
        }

        // Regular callers go to caller dashboard
        return 'rb.caller.dashboard';
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
