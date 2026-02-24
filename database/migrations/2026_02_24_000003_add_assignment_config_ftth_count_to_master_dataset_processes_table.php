<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_processes', 'assignment_config_ftth_count')) {
                $table->unsignedInteger('assignment_config_ftth_count')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            if (Schema::hasColumn('master_dataset_processes', 'assignment_config_ftth_count')) {
                $table->dropColumn('assignment_config_ftth_count');
            }
        });
    }
};
