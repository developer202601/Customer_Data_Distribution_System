<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_rows', 'sms_1')) {
                $table->string('sms_1', 100)->nullable()->after('last_api_fetched');
            }
            if (! Schema::hasColumn('master_dataset_rows', 'sms_2')) {
                $table->string('sms_2', 100)->nullable()->after('sms_1');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            if (Schema::hasColumn('master_dataset_rows', 'sms_2')) {
                $table->dropColumn('sms_2');
            }
            if (Schema::hasColumn('master_dataset_rows', 'sms_1')) {
                $table->dropColumn('sms_1');
            }
        });
    }
};
