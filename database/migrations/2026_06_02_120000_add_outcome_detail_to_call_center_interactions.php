<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_center_interactions', function (Blueprint $table) {
            $table->string('outcome_detail', 150)->nullable()->after('outcome');
        });
    }

    public function down(): void
    {
        Schema::table('call_center_interactions', function (Blueprint $table) {
            $table->dropColumn('outcome_detail');
        });
    }
};