<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('master_dataset_rows', function (Blueprint $table) {
                $table->dropForeign(['process_id']);
                $table->dropUnique('mdr_process_run_product_unique');
                try {
                    $table->dropUnique('mdr_process_run_productseq_unique');
                } catch (\Exception $e) {
                    // ignore if the index does not exist in SQLite
                }

                $table->unique(
                    ['process_id', 'run_date_raw', 'account_num', 'product_label', 'product_seq'],
                    'mdr_process_run_product_unique'
                );

                $table->foreign('process_id')
                      ->references('id')
                      ->on('master_dataset_processes')
                      ->cascadeOnDelete();
            });

            return;
        }

        $database = (string) DB::getDatabaseName();

        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'master_dataset_rows'
               AND COLUMN_NAME = 'process_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$database]
        );

        foreach ($foreignKeys as $fk) {
            $constraint = (string) ($fk->CONSTRAINT_NAME ?? '');
            if ($constraint !== '') {
                DB::statement(sprintf('ALTER TABLE `master_dataset_rows` DROP FOREIGN KEY `%s`', str_replace('`', '``', $constraint)));
            }
        }

        $indexes = DB::select(
            "SELECT DISTINCT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'master_dataset_rows'
               AND INDEX_NAME IN ('mdr_process_run_product_unique', 'mdr_process_run_productseq_unique')",
            [$database]
        );

        foreach ($indexes as $index) {
            $name = (string) ($index->INDEX_NAME ?? '');
            if ($name !== '' && $name !== 'PRIMARY') {
                DB::statement(sprintf('ALTER TABLE `master_dataset_rows` DROP INDEX `%s`', str_replace('`', '``', $name)));
            }
        }

        DB::statement("ALTER TABLE `master_dataset_rows` ADD UNIQUE `mdr_process_run_product_unique` (`process_id`, `run_date_raw`(32), `account_num`(64), `product_label`(128), `product_seq`(32))");

        Schema::table('master_dataset_rows', function (Blueprint $table) {
            $table->foreign('process_id')
                ->references('id')
                ->on('master_dataset_processes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('master_dataset_rows', function (Blueprint $table) {
                $table->dropForeign(['process_id']);
                $table->dropUnique('mdr_process_run_product_unique');
                try {
                    $table->dropUnique('mdr_process_run_productseq_unique');
                } catch (\Exception $e) {
                    // ignore if the index does not exist in SQLite
                }

                $table->unique([
                    'process_id',
                    'run_date_raw',
                    'product_label',
                    'account_num',
                ], 'mdr_process_run_product_unique');

                $table->foreign('process_id')
                      ->references('id')
                      ->on('master_dataset_processes')
                      ->cascadeOnDelete();
            });

            return;
        }

        $database = (string) DB::getDatabaseName();

        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'master_dataset_rows'
               AND COLUMN_NAME = 'process_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$database]
        );

        foreach ($foreignKeys as $fk) {
            $constraint = (string) ($fk->CONSTRAINT_NAME ?? '');
            if ($constraint !== '') {
                DB::statement(sprintf('ALTER TABLE `master_dataset_rows` DROP FOREIGN KEY `%s`', str_replace('`', '``', $constraint)));
            }
        }

        $indexes = DB::select(
            "SELECT DISTINCT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'master_dataset_rows'
               AND INDEX_NAME IN ('mdr_process_run_product_unique', 'mdr_process_run_productseq_unique')",
            [$database]
        );

        foreach ($indexes as $index) {
            $name = (string) ($index->INDEX_NAME ?? '');
            if ($name !== '' && $name !== 'PRIMARY') {
                DB::statement(sprintf('ALTER TABLE `master_dataset_rows` DROP INDEX `%s`', str_replace('`', '``', $name)));
            }
        }

        Schema::table('master_dataset_rows', function (Blueprint $table) {
            $table->unique([
                'process_id',
                'run_date_raw',
                'product_label',
                'account_num',
            ], 'mdr_process_run_product_unique');
            $table->foreign('process_id')
                ->references('id')
                ->on('master_dataset_processes')
                ->cascadeOnDelete();
        });
    }
};
