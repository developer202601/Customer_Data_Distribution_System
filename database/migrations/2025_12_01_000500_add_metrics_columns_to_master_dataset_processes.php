<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_processes', 'row_count')) {
                $table->unsignedInteger('row_count')->default(0)->after('user_name');
            }

            if (! Schema::hasColumn('master_dataset_processes', 'excluded_count')) {
                $table->unsignedInteger('excluded_count')->default(0)->after('row_count');
            }

            if (! Schema::hasColumn('master_dataset_processes', 'call_center_staff_count')) {
                $table->unsignedInteger('call_center_staff_count')->default(0)->after('excluded_count');
            }

            if (! Schema::hasColumn('master_dataset_processes', 'call_center_count')) {
                $table->unsignedInteger('call_center_count')->default(0)->after('call_center_staff_count');
            }

            if (! Schema::hasColumn('master_dataset_processes', 'staff_count')) {
                $table->unsignedInteger('staff_count')->default(0)->after('call_center_count');
            }

            if (! Schema::hasColumn('master_dataset_processes', 'region_billing_count')) {
                $table->unsignedInteger('region_billing_count')->default(0)->after('staff_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            foreach ([
                'region_billing_count',
                'staff_count',
                'call_center_count',
                'call_center_staff_count',
                'excluded_count',
                'row_count',
            ] as $column) {
                if (Schema::hasColumn('master_dataset_processes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
