// Step 1: Load user with id=63
$user = DB::table('users')->where('id', 63)->first();
echo "Step 1 - User loaded: " . ($user ? "true" : "false") . "\n";

if (!$user) {
    echo "Cannot continue - user not found\n";
    return;
}

// Step 2: Check if assignment starts with 'rtom_'
$startsWithRtom = strpos($user->assignment, 'rtom_') === 0;
echo "Step 2 - Assignment starts with 'rtom_': " . ($startsWithRtom ? "true" : "false") . "\n";
echo "  (assignment: {$user->assignment})\n";

// Step 3: Extract rtom value from 'rtom_kx'
$rtomValue = null;
if ($startsWithRtom) {
    $rtomValue = substr($user->assignment, 5); // Remove 'rtom_' prefix
}
echo "Step 3 - Extracted rtom value: " . ($rtomValue ?? "null") . "\n";

// Step 4: Query master_dataset_rows where rtom='kx' and get first region
$firstRegion = null;
$regionRowCount = 0;
if ($rtomValue) {
    $firstRow = DB::table('master_dataset_rows')
        ->whereRaw('LOWER(TRIM(rtom)) = ?', [$rtomValue])
        ->first();
    if ($firstRow) {
        $firstRegion = $firstRow->region;
    }
    // Count how many rows exist for this rtom/region combo
    $regionRowCount = DB::table('master_dataset_rows')
        ->whereRaw('LOWER(TRIM(rtom)) = ?', [$rtomValue])
        ->where('region', $firstRegion)
        ->count();
}
echo "Step 4 - First region for rtom='{$rtomValue}': " . ($firstRegion ?? "null") . "\n";

// Step 5: Verify rows exist for that region with rtom=kx rows (alternative check)
$rowsExistForRegion = $regionRowCount > 0;
echo "Step 5 - Rows exist for region '{$firstRegion}' with rtom='{$rtomValue}': " . ($rowsExistForRegion ? "true" : "false") . " (count: {$regionRowCount})\n";

echo "\n=== SUMMARY ===\n";
echo "User ID 63 loaded: " . ($user ? "true" : "false") . "\n";
echo "Assignment is rtom_*: " . ($startsWithRtom ? "true" : "false") . "\n";
echo "RTom value extracted: " . ($rtomValue ?? "null") . "\n";
echo "First region found: " . ($firstRegion ?? "null") . "\n";
echo "Data rows exist for region: " . ($rowsExistForRegion ? "true" : "false") . "\n";
