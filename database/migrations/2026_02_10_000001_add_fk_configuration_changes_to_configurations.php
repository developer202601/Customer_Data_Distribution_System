<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuration_changes') || !Schema::hasTable('configurations')) {
            return;
        }

        // Ensure no leftover FK is pointing at the removed legacy `configuration` table.
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
            // Ignore.
        }

        // MySQL requires referenced/referencing columns to match type and signedness.
        // Make both IDs INT UNSIGNED so we can add a FK to configurations(id).
        try {
            DB::statement('ALTER TABLE `configurations` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT');
        } catch (\Throwable $e) {
            // Ignore if already correct or if DB is not MySQL.
        }

        try {
            DB::statement('ALTER TABLE `configuration_changes` MODIFY `configuration_id` INT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Ignore if already correct or if DB is not MySQL.
        }

        // Ensure an index exists for the FK (safe to try; ignore if it already exists).
        try {
            DB::statement('ALTER TABLE `configuration_changes` ADD INDEX `configuration_changes_configuration_id_index` (`configuration_id`)');
        } catch (\Throwable $e) {
            // Ignore.
        }

        // Add FK to the active `configurations` table.
        try {
            DB::statement(
                'ALTER TABLE `configuration_changes` '
                . 'ADD CONSTRAINT `configuration_changes_configuration_id_fk` '
                . 'FOREIGN KEY (`configuration_id`) REFERENCES `configurations`(`id`) '
                . 'ON DELETE SET NULL ON UPDATE CASCADE'
            );
        } catch (\Throwable $e) {
            // Ignore if it already exists or if the DB doesn't support it.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('configuration_changes')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `configuration_changes` DROP FOREIGN KEY `configuration_changes_configuration_id_fk`');
        } catch (\Throwable $e) {
            // Ignore.
        }
    }
};
