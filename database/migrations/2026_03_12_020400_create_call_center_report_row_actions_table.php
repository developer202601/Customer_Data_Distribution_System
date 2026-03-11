<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_center_report_row_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_center_report_id')
                ->constrained('call_center_reports')
                ->cascadeOnDelete();
            $table->foreignId('master_dataset_row_id')
                ->constrained('master_dataset_rows')
                ->cascadeOnDelete();
            $table->enum('action', ['hide', 'unhide']);
            $table->unsignedBigInteger('acted_by_user_id')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['call_center_report_id', 'master_dataset_row_id'], 'cc_report_row_actions_row_idx');
            $table->index('action', 'cc_report_row_actions_action_idx');
            $table->index('acted_by_user_id', 'cc_report_row_actions_user_idx');
            $table->index('acted_at', 'cc_report_row_actions_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_center_report_row_actions');
    }
};
