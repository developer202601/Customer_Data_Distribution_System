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
            if (($sessionUser['assignment'] ?? null) === 'super') {
                $target = 'cc.super.segments';
            } else {
                $target = ($sessionUser['is_admin'] ?? false) ? 'cc.users.index' : 'cc.dashboard';
            }

            return redirect()->route($target);
            /*
            $target = ($sessionUser['is_admin'] ?? false) ? 'cc.users.index' : 'cc.dashboard';

            return redirect()->route($target);
            */
        }

        if (!empty($sessionUser) && (($sessionUser['system'] ?? null) === 'rb')) {
            return redirect()->route('rb.dashboard');
        }

        return view('dashboard');
    }
}
