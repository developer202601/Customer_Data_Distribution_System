<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_center_reports', function (Blueprint $table) {
            try {
                $table->dropForeign('call_center_reports_master_dataset_process_id_foreign');
            } catch (\Throwable) {
                // Ignore if already dropped in another environment.
            }

            try {
                $table->dropUnique('call_center_reports_master_dataset_process_id_unique');
            } catch (\Throwable) {
                // Ignore if already dropped in another environment.
            }

            try {
                $table->unique(
                    ['master_dataset_process_id', 'report_type'],
                    'cc_reports_process_type_unique'
                );
            } catch (\Throwable) {
                // Ignore if already present.
            }

            try {
                $table->foreign('master_dataset_process_id')
                    ->references('id')
                    ->on('master_dataset_processes')
                    ->cascadeOnDelete();
            } catch (\Throwable) {
                // Ignore if already present.
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_center_reports', function (Blueprint $table) {
            try {
                $table->dropForeign('call_center_reports_master_dataset_process_id_foreign');
            } catch (\Throwable) {
                // Ignore if already dropped.
            }

            try {
                $table->dropUnique('cc_reports_process_type_unique');
            } catch (\Throwable) {
                // Ignore if already dropped.
            }

            try {
                $table->unique(
                    ['master_dataset_process_id'],
                    'call_center_reports_master_dataset_process_id_unique'
                );
            } catch (\Throwable) {
                // Ignore if already present.
            }

            try {
                $table->foreign('master_dataset_process_id')
                    ->references('id')
                    ->on('master_dataset_processes')
                    ->cascadeOnDelete();
            } catch (\Throwable) {
                // Ignore if already present.
            }
        });
    }
};
