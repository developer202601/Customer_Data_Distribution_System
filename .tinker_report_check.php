// Setup request with proper session data
$request = new Illuminate\Http\Request();

session([
    'user' => [
        'id' => 63,
        'name' => '001000',
        'assignment' => 'rtom_kx',
        'system' => 'rb'
    ]
]);

$request->setUserResolver(function () {
    return App\Models\User::find(63);
});

// Create ReportController and get response
$controller = new App\Http\Controllers\RegionalBilling\ReportController();
$response = $controller->index($request);

$viewData = $response->getData();
$reports = $viewData['reports'] ?? null;

echo "=== REPORTS COLLECTION ANALYSIS ===\n\n";

if ($reports) {
    echo "Reports Type: " . get_class($reports) . "\n";
    echo "Reports Count: " . count($reports) . "\n";
    echo "Reports isEmpty: " . ($reports->isEmpty() ? "true" : "false") . "\n\n";
    
    if (!$reports->isEmpty()) {
        echo "? Reports collection is NOT empty\n\n";
        
        echo "All Reports in Collection:\n";
        echo str_repeat("-", 80) . "\n";
        
        foreach ($reports as $index => $report) {
            echo "Report [" . ($index + 1) . "]:\n";
            echo "  ID: " . ($report->id ?? "N/A") . "\n";
            echo "  Name: " . ($report->name ?? "N/A") . "\n";
            echo "  Type: " . ($report->report_type ?? "N/A") . "\n";
            echo "  Dataset Month: " . ($report->dataset_month ?? "N/A") . "\n";
            echo "  Row Count: " . ($report->row_count ?? "N/A") . "\n";
            echo "  Created At: " . ($report->created_at ?? "N/A") . "\n";
            echo "\n";
        }
        
        echo str_repeat("=", 80) . "\n";
        echo "LATEST/FIRST REPORT:\n";
        echo str_repeat("-", 80) . "\n";
        $latest = $reports->first();
        echo "  ID: " . ($latest->id ?? "N/A") . "\n";
        echo "  Type: " . ($latest->report_type ?? "N/A") . "\n";
        echo "  Dataset Month: " . ($latest->dataset_month ?? "N/A") . "\n";
        echo "  Row Count: " . ($latest->row_count ?? "N/A") . "\n";
        echo "  Created At: " . ($latest->created_at ?? "N/A") . "\n";
        
        // Check if report type is RB
        if (($latest->report_type ?? null) === "regional-billing") {
            echo "? Report type is RB (regional-billing)\n";
        }
    } else {
        echo "? Reports collection IS EMPTY\n";
    }
} else {
    echo "? NO REPORTS IN VIEW DATA\n";
}

echo "\n=== SESSION INFO ===\n";
$sessionUser = session("user");
echo "Session User: " . json_encode($sessionUser) . "\n";

echo "\n=== VIEW INFO ===\n";
echo "View Name: " . $response->getName() . "\n";
echo "Region: " . ($viewData["region"] ?? "N/A") . "\n";
echo "RTOM: " . ($viewData["rtom"] ?? "N/A") . "\n";
