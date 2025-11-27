<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use Illuminate\Http\Request;

class BillRangeController extends Controller
{
    public function createRange(Request $request){
        $incomingFields = $request->validate([
            'upper_range' => 'required|integer',
            'lower_range' => 'required|integer',
        ]);

        $incomingFields['upper_range'] = strip_tags($incomingFields['upper_range']);
        $incomingFields['lower_range'] = strip_tags($incomingFields['lower_range']);
        
        
        Configuration::create($incomingFields);
    }
}
