<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function config()
    {
        return view('admin/adminconfig');
    }
}
