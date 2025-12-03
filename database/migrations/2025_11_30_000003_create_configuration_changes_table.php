<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('configuration_changes')) {
            Schema::create('configuration_changes', function (Blueprint $table) {
                $table->id();
                // store reference ids but avoid adding rigid foreign key constraints
                // to remain compatible with existing DB schemas
                $table->unsignedBigInteger('configuration_id')->nullable()->index();
                $table->string('config_key');
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuration_changes');
    }
};
