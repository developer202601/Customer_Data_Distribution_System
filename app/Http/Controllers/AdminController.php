<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Configuration;

class AdminController extends Controller
{
    public function createRange(Request $request){
        $incomingFields = $request->validate([
            'upper_range' => 'required|integer',
            'lower_range' => 'required|integer',
        ]);

        $incomingFields['upper_range'] = strip_tags($incomingFields['upper_range']);
        $incomingFields['lower_range'] = strip_tags($incomingFields['lower_range']);

        Configuration::create($incomingFields);

        return back()->with('success', 'Saved!');
    }

    public function config()
    {
        return view('admin/adminconfig');
    }
}
