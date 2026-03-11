<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_center_report_region_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_center_report_id')
                ->constrained('call_center_reports')
                ->cascadeOnDelete();
            $table->string('region_name', 255);
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['call_center_report_id', 'region_name'], 'cc_region_review_unique');
            $table->index(['region_name', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_center_report_region_reviews');
    }
};
