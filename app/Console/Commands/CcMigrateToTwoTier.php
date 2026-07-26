<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CcMigrateToTwoTier extends Command
{
    protected $signature = 'cc:migrate-to-two-tier 
                            {--dry-run : Preview changes without writing to database}';
    protected $description = 'Migrate Call Center users from 5-tier to 2-tier hierarchy (Super Admin, Region Admin, Caller)';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $mode = $isDryRun ? 'DRY RUN' : 'LIVE';
        $this->warn("=== {$mode}: Migrating CC users to 2-tier hierarchy ===");

        $ccUsers = User::where('system', 'cc')->get();
        $total = $ccUsers->count();
        $this->info("Found {$total} CC users.");

        $kept = 0;
        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($ccUsers as $user) {
            $assignment = (string) ($user->assignment ?? '');

            if ($assignment === 'super' || $assignment === '') {
                $this->line("  [KEEP]   User {$user->username} ({$user->id}) - assignment: '{$assignment}'");
                $kept++;
                continue;
            }

            if (!str_starts_with($assignment, 'rtom_') && !str_starts_with($assignment, 'supervisor_') && !str_starts_with($assignment, 'caller_')) {
                $this->line("  [KEEP]   User {$user->username} ({$user->id}) - assignment: '{$assignment}' (region admin)");
                $kept++;
                continue;
            }

            $regionAdmin = $this->findRegionAdmin($user);
            if (!$regionAdmin) {
                $this->error("  [ERROR]  User {$user->username} ({$user->id}) - could not find region admin in supervisor chain. Skipping.");
                $errors++;
                continue;
            }

            $regionName = (string) ($regionAdmin->assignment ?? '');
            $normalizedRegion = strtolower(trim(preg_replace('/\s+/', '_', $regionName)));
            $newAssignment = 'caller_' . $normalizedRegion;

            if ($newAssignment === $assignment) {
                $this->line("  [SKIP]   User {$user->username} ({$user->id}) - already has correct assignment '{$assignment}'");
                $skipped++;
                continue;
            }

            $this->line("  [MIGRATE] User {$user->username} ({$user->id}) - '{$assignment}' -> '{$newAssignment}', supervisor -> {$regionAdmin->id}");

            if (!$isDryRun) {
                try {
                    DB::transaction(function () use ($user, $newAssignment, $regionAdmin) {
                        $user->assignment = $newAssignment;
                        $user->supervisor = $regionAdmin->id;
                        $user->admin_prev = 0;
                        $user->save();
                    });
                } catch (\Exception $e) {
                    $this->error("    DB ERROR: " . $e->getMessage());
                    $errors++;
                    continue;
                }
            }

            $migrated++;
        }

        $this->newLine();
        $this->info("=== Migration Summary ===");
        $this->table(
            ['Status', 'Count'],
            [
                ['Kept (Super/Region Admin)', $kept],
                ['Migrated (flattened to Caller)', $migrated],
                ['Skipped (already correct)', $skipped],
                ['Errors', $errors],
                ['Total', $total],
            ]
        );

        if ($isDryRun) {
            $this->warn('This was a DRY RUN. No changes were saved.');
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->info('Migration completed.');
        }

        return 0;
    }

    private function findRegionAdmin(User $user): ?User
    {
        $current = $user;
        $visited = [];

        while ($current) {
            if (isset($visited[$current->id])) {
                break;
            }
            $visited[$current->id] = true;

            $assignment = (string) ($current->assignment ?? '');

            if ($assignment === 'super') {
                return null;
            }

            if ($assignment !== '' && !str_starts_with($assignment, 'rtom_') && !str_starts_with($assignment, 'supervisor_') && !str_starts_with($assignment, 'caller_')) {
                return $current;
            }

            if (!empty($current->supervisor)) {
                $current = User::find($current->supervisor);
            } else {
                break;
            }
        }

        return null;
    }
}
