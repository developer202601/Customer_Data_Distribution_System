<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('original_name');
            $table->string('status')->default('processing');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('message')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('started_at')->nullable();
            $table->unsignedInteger('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_uploads');
    }
};
