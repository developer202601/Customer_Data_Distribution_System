<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $sessionUser = $request->session()->get('user');

        if (!empty($sessionUser) && (($sessionUser['system'] ?? null) === 'cc')) {
            $target = ($sessionUser['is_admin'] ?? false) ? 'cc.users.index' : 'cc.dashboard';

            return redirect()->route($target);
        }

        return view('dashboard');
    }
}
