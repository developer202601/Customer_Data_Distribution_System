<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\CallCenterReport;
use App\Models\CallCenterAssignment;

$region = 'REGION 01';
$rtom = 'WA';

echo "Checking REGION $region, RTOM $rtom\n";

// Find supervisors for this RTOM
$supervisorAssignment = 'supervisor_rtom_' . strtolower(str_replace(' ', '_', $rtom));
echo "Supervisor assignment: $supervisorAssignment\n";

$supervisors = User::where('assignment', $supervisorAssignment)->get();
echo "Supervisors found: " . $supervisors->count() . "\n";
foreach ($supervisors as $sup) {
    echo "  - {$sup->name} (ID: {$sup->id})\n";
}

// Get latest report for the region
$latestReport = CallCenterReport::whereHas('assignments', function($q) use ($region) {
    $q->whereHas('row', function($rq) use ($region) {
        $rq->where('region', $region);
    });
})->latest('created_at')->first();

echo "Latest report ID: " . ($latestReport ? $latestReport->id : 'None') . "\n";

// Get assignments for this RTOM in the region
$assignments = CallCenterAssignment::whereHas('row', function($q) use ($region, $rtom) {
    $q->where('region', $region)->where('rtom', $rtom);
})->get();

echo "Assignments for RTOM $rtom in REGION $region: " . $assignments->count() . "\n";

// Calculate profits for each supervisor
foreach ($supervisors as $supervisor) {
    $profit = CallCenterAssignment::whereHas('row', function($q) use ($region, $rtom) {
        $q->where('region', $region)->where('rtom', $rtom);
    })->whereHas('agent', function($q) use ($supervisor) {
        $q->where('supervisor', $supervisor->id);
    })->sum('paid_amount');

    echo "Supervisor {$supervisor->name}: Profit $profit\n";
}