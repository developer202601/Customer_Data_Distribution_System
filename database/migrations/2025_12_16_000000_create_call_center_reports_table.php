<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_center_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_dataset_process_id')
                ->constrained('master_dataset_processes')
                ->cascadeOnDelete();
            $table->string('token', 64);
            $table->string('dataset_month', 16)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->json('row_ids')->nullable();
            $table->timestamps();

            $table->unique('master_dataset_process_id');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_center_reports');
    }
};