<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('username', '021244')->first();
if ($user) {
    echo "User found:\n";
    echo "  id: {$user->id}\n";
    echo "  username: {$user->username}\n";
    echo "  system: '" . ($user->system ?? 'NULL') . "'\n";
    echo "  assignment: '" . ($user->assignment ?? 'NULL') . "'\n";
    echo "  admin_prev: " . ($user->admin_prev ? 'true' : 'false') . "\n";
    echo "  status: " . ($user->status ? 'true' : 'false') . "\n";
    echo "  name: '" . ($user->name ?? 'NULL') . "'\n";
    echo "  fixed: " . ($user->fixed ? 'true' : 'false') . "\n";
    
    // Check what the super admin query returns
    $query = \App\Models\User::query()
        ->with('supervisorUser')
        ->withCount(['supervisedUsers', 'interactionsAsAgent', 'rowAssignments']);
    
    $query->where(function ($q) {
        $q->where('system', 'cc')
          ->orWhere(function ($sq) {
              $sq->whereNull('assignment')
                ->orWhere('assignment', '');
          });
    });
    
    $ids = $query->pluck('username', 'id');
    echo "\nUsers visible to super admin:\n";
    foreach ($ids as $uid => $uname) {
        echo "  id=$uid username=$uname\n";
    }
    
    if ($ids->search('021244') !== false) {
        echo "\n021244 IS in the results\n";
    } else {
        echo "\n021244 is NOT in the results\n";
    }
} else {
    echo "User 021244 not found in database.\n";
}
