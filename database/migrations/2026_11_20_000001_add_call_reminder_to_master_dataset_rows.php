<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_rows', 'call_reminder')) {
                $table->string('call_reminder', 50)->nullable()->after('sales_channel');
            }
        });

        Schema::table('master_dataset_rows_staging', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_rows_staging', 'call_reminder')) {
                $table->string('call_reminder', 50)->nullable()->after('sales_channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            if (Schema::hasColumn('master_dataset_rows', 'call_reminder')) {
                $table->dropColumn('call_reminder');
            }
        });

        Schema::table('master_dataset_rows_staging', function (Blueprint $table) {
            if (Schema::hasColumn('master_dataset_rows_staging', 'call_reminder')) {
                $table->dropColumn('call_reminder');
            }
        });
    }
};
