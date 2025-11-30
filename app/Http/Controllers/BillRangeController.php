<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\ConfigurationChange;
use Illuminate\Http\Request;

class BillRangeController extends Controller
{
    public function createRange(Request $request){
        $incomingFields = $request->validate([
            'upper_range' => 'required|integer',
            'lower_range' => 'required|integer'
        ]);

        $incomingFields['upper_range'] = (int) strip_tags($incomingFields['upper_range']);
        $incomingFields['lower_range'] = (int) strip_tags($incomingFields['lower_range']);

        // Determine previous values (if any) so we can record history
        $previous = Configuration::orderBy('id', 'desc')->first();

        // Determine user id: prefer Laravel auth(), fallback to session 'user' used by middleware
        $userId = auth()->id() ?: $request->session()->get('user.id');

        if (!$userId) {
            return redirect()->route('login')->withErrors(['auth' => 'You must be logged in to perform this action.']);
        }

        // Associate to the current user (DB has a non-nullable foreign key)
        $incomingFields['user_id'] = (int) $userId;

        // Create the new configuration row
        $config = Configuration::create($incomingFields);

        // Record changes for audit/history
        try {
            ConfigurationChange::create([
                'configuration_id' => $config->id,
                'config_key' => 'upper_range',
                'old_value' => $previous ? (string) $previous->upper_range : null,
                'new_value' => (string) $config->upper_range,
                'user_id' => $incomingFields['user_id'],
            ]);

            ConfigurationChange::create([
                'configuration_id' => $config->id,
                'config_key' => 'lower_range',
                'old_value' => $previous ? (string) $previous->lower_range : null,
                'new_value' => (string) $config->lower_range,
                'user_id' => $incomingFields['user_id'],
            ]);
        } catch (\Exception $e) {
            // Don't break the user flow if logging fails; log to the default logger
            logger()->error('Failed to record configuration change: ' . $e->getMessage());
        }

        return redirect()->route('admin.config')->with('success', 'Bill range saved successfully.');
    }
}
