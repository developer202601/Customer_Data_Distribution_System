<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            if (Schema::hasColumn('master_dataset_processes', 'assignment_config_set_by_user_name')) {
                $table->dropColumn('assignment_config_set_by_user_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_processes', 'assignment_config_set_by_user_name')) {
                $table->string('assignment_config_set_by_user_name')->nullable();
            }
        });
    }
};
