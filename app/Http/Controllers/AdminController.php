<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Configuration;

class AdminController extends Controller
{
    

    public function config()
    {
        return view('admin/adminconfig');
    }
}
