<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_center_report_hidden_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_center_report_id')
                ->constrained('call_center_reports')
                ->cascadeOnDelete();
            $table->foreignId('master_dataset_row_id')
                ->constrained('master_dataset_rows')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('hidden_by_user_id')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->unique(['call_center_report_id', 'master_dataset_row_id'], 'cc_hidden_rows_unique');
            $table->index('hidden_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_center_report_hidden_rows');
    }
};
