<?php

namespace App\Http\Controllers;

use App\Models\Configurations;
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
        $previous = Configurations::whereIn('config_name', ['upper_range','lower_range'])->get()->keyBy('config_name');
        // $previousUpper = $previous->get('upper_range');
        // $previousLower = $previous->get('lower_range');

        // Determine user id: prefer Laravel auth(), fallback to session 'user' used by middleware
        $userId = auth()->id() ?: $request->session()->get('user.id');

        if (!$userId) {
            return redirect()->route('login')->withErrors(['auth' => 'You must be logged in to perform this action.']);
        }

        // Associate to the current user (DB has a non-nullable foreign key)
        $incomingFields['user_id'] = (int) $userId;

        

        // Record changes for audit/history
        try {

            $config = Configurations::updateorCreate(
                ['config_name' => 'upper_range'],
                ['value' => $incomingFields['upper_range'], 'changedby_id' => $incomingFields['user_id']]
            );

            $config = Configurations::updateorCreate(
                ['config_name' => 'lower_range'],
                ['value' => $incomingFields['lower_range'], 'changedby_id' => $incomingFields['user_id']]
            );

            ConfigurationChange::create([
                'configuration_id' => $config->id,
                'config_key' => 'upper_range',
                'old_value' => $previous->get('upper_range') ? (string) $previous->get('upper_range')->value : null,
                'new_value' => (string) $incomingFields['upper_range'],
                'user_id' => $incomingFields['user_id'],
            ]);

            ConfigurationChange::create([
                'configuration_id' => $config->id,
                'config_key' => 'lower_range',
                'old_value' => $previous->get('lower_range') ? (string) $previous->get('lower_range')->value : null,
                'new_value' => (string) $incomingFields['lower_range'],
                'user_id' => $incomingFields['user_id'],
            ]);
        } catch (\Exception $e) {
            // Don't break the user flow if logging fails; log to the default logger
            logger()->error('Failed to record configuration change: ' . $e->getMessage());
        }

        return redirect()->route('admin.config')->with('success', 'Bill range saved successfully.');
    }
}
