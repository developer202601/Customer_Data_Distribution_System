// 1. Find user with assignment='rtom_kx' and system='rb'
$rtomAdmin = DB::table('users')
    ->where('assignment', 'rtom_kx')
    ->where('system', 'rb')
    ->first();

if (!$rtomAdmin) {
    echo "rtom_admin_id=none\n";
    echo "rtom_admin_supervisor_id=none\n";
    echo "supervisor_assignment=none\n";
    echo "master_rows_regions=none\n";
    return;
}

echo "rtom_admin_id={$rtomAdmin->id}\n";

// 2. Check if they have a supervisor field and get supervisor's assignment
$supervisorId = $rtomAdmin->supervisor_id ?? null;
echo "rtom_admin_supervisor_id=" . ($supervisorId ?? 'none') . "\n";

$supervisorAssignment = 'none';
if ($supervisorId) {
    $supervisor = DB::table('users')->where('id', $supervisorId)->first();
    if ($supervisor && isset($supervisor->assignment)) {
        $supervisorAssignment = $supervisor->assignment;
    }
}
echo "supervisor_assignment={$supervisorAssignment}\n";

// 3. Find all master_dataset_rows where rtom='kx' (lowercase match) and show distinct regions
$regionsCollection = DB::table('master_dataset_rows')
    ->whereRaw('LOWER(TRIM(rtom)) = ?', ['kx'])
    ->distinct()
    ->pluck('region')
    ->filter(function($v) { return $v !== null && $v !== ""; })
    ->values();

$regions = $regionsCollection->toArray();
echo "master_rows_regions=" . implode(',', $regions) . "\n";
