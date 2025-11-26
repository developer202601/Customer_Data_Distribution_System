<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillRangeController extends Controller
{
    public function createRange(Request $request){
        $incomingFields = $request->validate([
            'upper_range' => 'required',
            'lower_range' => 'required'
        ]);
    }
}
