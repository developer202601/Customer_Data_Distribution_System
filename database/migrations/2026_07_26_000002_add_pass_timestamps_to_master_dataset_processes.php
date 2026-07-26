<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_processes', 'passed_ccs_at')) {
                $table->timestamp('passed_ccs_at')->nullable()->after('updated_at')
                    ->comment('When Call Center Staff records were manually passed to CC');
            }
            if (! Schema::hasColumn('master_dataset_processes', 'passed_cc_at')) {
                $table->timestamp('passed_cc_at')->nullable()->after('passed_ccs_at')
                    ->comment('When Call Center records were manually passed to CC');
            }
            if (! Schema::hasColumn('master_dataset_processes', 'passed_s_at')) {
                $table->timestamp('passed_s_at')->nullable()->after('passed_cc_at')
                    ->comment('When Staff records were manually passed to CC');
            }
            if (! Schema::hasColumn('master_dataset_processes', 'passed_rb_at')) {
                $table->timestamp('passed_rb_at')->nullable()->after('passed_s_at')
                    ->comment('When Regional Billing records were manually passed to RB');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            foreach (['passed_ccs_at', 'passed_cc_at', 'passed_s_at', 'passed_rb_at'] as $column) {
                if (Schema::hasColumn('master_dataset_processes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
