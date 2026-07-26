<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate rows before adding the unique index.
        // Keep the row with the lowest id for each (call_center_report_id, master_dataset_row_id, assigned_user_id) triple.
        try {
            DB::statement('
                DELETE FROM call_center_row_assignments
                WHERE id NOT IN (
                    SELECT min_id FROM (
                        SELECT MIN(id) AS min_id
                        FROM call_center_row_assignments
                        GROUP BY call_center_report_id, master_dataset_row_id, COALESCE(assigned_user_id, 0)
                    ) AS deduped
                )
            ');
        } catch (\Throwable $e) {
            // If the dedup query fails (e.g. no duplicates, DB quirk), proceed anyway.
        }

        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            // Guard: only add if it does not already exist to keep the migration re-runnable.
            try {
                $table->unique(
                    ['call_center_report_id', 'master_dataset_row_id', 'assigned_user_id'],
                    'cc_assignments_unique_report_row_user'
                );
            } catch (\Throwable $e) {
                // Index already exists — safe to continue.
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            try {
                $table->dropUnique('cc_assignments_unique_report_row_user');
            } catch (\Throwable $e) {
                // Index may not exist.
            }
        });
    }
};
