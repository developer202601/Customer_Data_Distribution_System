<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_processes', 'assignment_config_source')) {
                $table->string('assignment_config_source')->nullable()->index();
            }

            if (! Schema::hasColumn('master_dataset_processes', 'assignment_config_overrides')) {
                $table->json('assignment_config_overrides')->nullable();
            }

            if (! Schema::hasColumn('master_dataset_processes', 'assignment_config_set_by_user_id')) {
                $table->unsignedBigInteger('assignment_config_set_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('master_dataset_processes', 'assignment_config_set_by_user_name')) {
                $table->string('assignment_config_set_by_user_name')->nullable();
            }

            if (! Schema::hasColumn('master_dataset_processes', 'assignment_config_set_at')) {
                $table->timestamp('assignment_config_set_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            $columns = [
                'assignment_config_source',
                'assignment_config_overrides',
                'assignment_config_set_by_user_id',
                'assignment_config_set_by_user_name',
                'assignment_config_set_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('master_dataset_processes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
