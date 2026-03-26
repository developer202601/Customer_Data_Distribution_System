<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            $table->string('latest_exclusion_token')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_processes', function (Blueprint $table) {
            $table->dropColumn('latest_exclusion_token');
        });
    }
};
