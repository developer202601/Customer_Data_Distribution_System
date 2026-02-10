<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some environments (like imported dumps) may have a FK from configuration_changes.configuration_id
        // to the legacy configuration table. Drop any such FK(s) before dropping the table.
        try {
            $constraints = DB::select(
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'configuration_changes'
                   AND COLUMN_NAME = 'configuration_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL"
            );

            foreach ($constraints as $row) {
                $constraintName = $row->CONSTRAINT_NAME ?? null;
                if (!$constraintName) {
                    continue;
                }
                DB::statement("ALTER TABLE `configuration_changes` DROP FOREIGN KEY `{$constraintName}`");
            }
        } catch (\Throwable $e) {
            // Ignore: DB driver may not support information_schema, or constraints may not exist.
        }

        Schema::dropIfExists('configuration');
    }

    public function down(): void
    {
        // Intentionally left empty: this migration is for removing a legacy table.
    }
};
