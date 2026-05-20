$getIds = function ($raw) {
    if (is_array($raw)) {
        return array_values(array_filter($raw, function($v){ return $v !== null && $v !== ""; }));
    }
    if ($raw === null) return [];
    if (is_string($raw)) {
        $trimmed = trim($raw);
        if ($trimmed === "") return [];
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded, function($v){ return $v !== null && $v !== ""; }));
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), function($v){ return $v !== ""; }));
    }
    return [];
};

$reportCount = DB::table('call_center_reports')->where('report_type', 'regional-billing')->count();
echo "report_count={$reportCount}\n";

$latest = DB::table('call_center_reports')
    ->where('report_type', 'regional-billing')
    ->orderByDesc('created_at')
    ->first();

if (!$latest) {
    echo "latest_report_id=none\n";
    echo "latest_report_token=none\n";
    echo "latest_created_at=none\n";
    echo "latest_row_count=0\n";
    echo "latest_row_ids_count=0\n";
    echo "region_metro_count=0\n";
    echo "region_01_count=0\n";
    echo "region_02_count=0\n";
    echo "region_03_count=0\n";
    echo "assignment_count=0\n";
    echo "hidden_rows_count=0\n";
    return;
}

$token = null;
if (isset($latest->token)) $token = $latest->token;
elseif (isset($latest->report_token)) $token = $latest->report_token;
elseif (isset($latest->uuid)) $token = $latest->uuid;

$ids = $getIds($latest->row_ids ?? null);
$rowIdsCount = count($ids);

$metro = 0; $r01 = 0; $r02 = 0; $r03 = 0;
if ($rowIdsCount > 0) {
    $base = DB::table('master_dataset_rows')->whereIn('id', $ids);
    $metro = (clone $base)->whereRaw('LOWER(TRIM(region)) = ?', ['metro'])->count();
    $r01 = (clone $base)->whereRaw('TRIM(region) = ?', ['01'])->count();
    $r02 = (clone $base)->whereRaw('TRIM(region) = ?', ['02'])->count();
    $r03 = (clone $base)->whereRaw('TRIM(region) = ?', ['03'])->count();
}

$assignmentQuery = DB::table('call_center_row_assignments')->where('report_type', 'regional-billing');
if (Schema::hasColumn('call_center_row_assignments', 'call_center_report_id')) {
    $assignmentQuery->where('call_center_report_id', $latest->id);
} elseif (Schema::hasColumn('call_center_row_assignments', 'report_id')) {
    $assignmentQuery->where('report_id', $latest->id);
} elseif (Schema::hasColumn('call_center_row_assignments', 'call_center_reports_id')) {
    $assignmentQuery->where('call_center_reports_id', $latest->id);
}
$assignmentCount = $assignmentQuery->count();

$hiddenQuery = DB::table('call_center_report_hidden_rows');
if (Schema::hasColumn('call_center_report_hidden_rows', 'report_type')) {
    $hiddenQuery->where('report_type', 'regional-billing');
}
if (Schema::hasColumn('call_center_report_hidden_rows', 'call_center_report_id')) {
    $hiddenQuery->where('call_center_report_id', $latest->id);
} elseif (Schema::hasColumn('call_center_report_hidden_rows', 'report_id')) {
    $hiddenQuery->where('report_id', $latest->id);
} elseif (Schema::hasColumn('call_center_report_hidden_rows', 'call_center_reports_id')) {
    $hiddenQuery->where('call_center_reports_id', $latest->id);
}
$hiddenCount = $hiddenQuery->count();

echo "latest_report_id={$latest->id}\n";
echo "latest_report_token=" . ($token ?? 'null') . "\n";
echo "latest_created_at={$latest->created_at}\n";
echo "latest_row_count={$latest->row_count}\n";
echo "latest_row_ids_count={$rowIdsCount}\n";
echo "region_metro_count={$metro}\n";
echo "region_01_count={$r01}\n";
echo "region_02_count={$r02}\n";
echo "region_03_count={$r03}\n";
echo "assignment_count={$assignmentCount}\n";
echo "hidden_rows_count={$hiddenCount}\n";
