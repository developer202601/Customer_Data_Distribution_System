<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_center_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_reports', 'report_type')) {
                $table->string('report_type', 32)->default('call-center')->after('master_dataset_process_id');
                $table->index('report_type', 'cc_reports_report_type_idx');
            }
        });

        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_row_assignments', 'report_type')) {
                $table->string('report_type', 32)->default('call-center')->after('call_center_report_id');
                $table->index('report_type', 'cc_assignments_report_type_idx');
            }
        });

        Schema::table('call_center_report_hidden_rows', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_report_hidden_rows', 'report_type')) {
                $table->string('report_type', 32)->default('call-center')->after('call_center_report_id');
                $table->index('report_type', 'cc_hidden_report_type_idx');
            }
        });

        Schema::table('call_center_report_region_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_report_region_reviews', 'report_type')) {
                $table->string('report_type', 32)->default('call-center')->after('call_center_report_id');
                $table->index('report_type', 'cc_region_review_report_type_idx');
            }
        });

        Schema::table('call_center_report_row_actions', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_report_row_actions', 'report_type')) {
                $table->string('report_type', 32)->default('call-center')->after('call_center_report_id');
                $table->index('report_type', 'cc_row_actions_report_type_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_center_report_row_actions', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_report_row_actions', 'report_type')) {
                $table->dropIndex('cc_row_actions_report_type_idx');
                $table->dropColumn('report_type');
            }
        });

        Schema::table('call_center_report_region_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_report_region_reviews', 'report_type')) {
                $table->dropIndex('cc_region_review_report_type_idx');
                $table->dropColumn('report_type');
            }
        });

        Schema::table('call_center_report_hidden_rows', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_report_hidden_rows', 'report_type')) {
                $table->dropIndex('cc_hidden_report_type_idx');
                $table->dropColumn('report_type');
            }
        });

        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_row_assignments', 'report_type')) {
                $table->dropIndex('cc_assignments_report_type_idx');
                $table->dropColumn('report_type');
            }
        });

        Schema::table('call_center_reports', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_reports', 'report_type')) {
                $table->dropIndex('cc_reports_report_type_idx');
                $table->dropColumn('report_type');
            }
        });
    }
};
