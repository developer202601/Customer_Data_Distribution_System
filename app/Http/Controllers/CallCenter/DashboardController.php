<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('callcenter.dashboard');
    }
}
