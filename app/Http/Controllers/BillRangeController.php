<?php

namespace App\Http\Controllers;

use App\Models\Configurations;
use App\Models\ConfigurationChange;
use Illuminate\Http\Request;

class BillRangeController extends Controller
{
    public function createRange(Request $request){
        $incomingFields = $request->validate([
            'upper_range' => 'required|integer|min:0|gt:lower_range',
            'lower_range' => 'required|integer|min:0'
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




    public function createStaff(Request $request){
        $incomingFields = $request->validate([
            'ccs' => 'required|integer|min:0',
            'cc' => 'required|integer|min:0',
            's' => 'required|integer|min:0'
        ]);

        $incomingFields['ccs'] = (int) strip_tags($incomingFields['ccs']);
        $incomingFields['cc'] = (int) strip_tags($incomingFields['cc']);
        $incomingFields['s'] = (int) strip_tags($incomingFields['s']);

        // Determine previous values (if any) so we can record history
        $previous = Configurations::whereIn('config_name', ['ccs','cc','s'])->get()->keyBy('config_name');
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
                ['config_name' => 'ccs'],
                ['value' => $incomingFields['ccs'], 'changedby_id' => $incomingFields['user_id']]
            );

            $config = Configurations::updateorCreate(
                ['config_name' => 'cc'],
                ['value' => $incomingFields['cc'], 'changedby_id' => $incomingFields['user_id']]
            );

            $config = Configurations::updateorCreate(
                ['config_name' => 's'],
                ['value' => $incomingFields['s'], 'changedby_id' => $incomingFields['user_id']]
            );

            ConfigurationChange::create([
                'configuration_id' => $config->id,
                'config_key' => 'ccs',
                'old_value' => $previous->get('ccs') ? (string) $previous->get('ccs')->value : null,
                'new_value' => (string) $incomingFields['ccs'],
                'user_id' => $incomingFields['user_id'],
            ]);

            ConfigurationChange::create([
                'configuration_id' => $config->id,
                'config_key' => 'cc',
                'old_value' => $previous->get('cc') ? (string) $previous->get('cc')->value : null,
                'new_value' => (string) $incomingFields['cc'],
                'user_id' => $incomingFields['user_id'],
            ]);

            ConfigurationChange::create([
                'configuration_id' => $config->id,
                'config_key' => 's',
                'old_value' => $previous->get('s') ? (string) $previous->get('s')->value : null,
                'new_value' => (string) $incomingFields['s'],
                'user_id' => $incomingFields['user_id'],
            ]);

        } catch (\Exception $e) {
            // Don't break the user flow if logging fails; log to the default logger
            logger()->error('Failed to record configuration change: ' . $e->getMessage());
        }

        return redirect()->route('admin.config')->with('success', 'staff saved successfully.');
    }
}
