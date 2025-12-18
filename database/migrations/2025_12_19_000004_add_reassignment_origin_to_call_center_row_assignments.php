<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_row_assignments', 'reassignment_origin_id')) {
                $table->unsignedBigInteger('reassignment_origin_id')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_row_assignments', 'reassignment_origin_id')) {
                $table->dropColumn('reassignment_origin_id');
            }
        });
    }
};
