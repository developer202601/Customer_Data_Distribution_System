<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dataset_exports');

        Schema::create('dataset_exports', function (Blueprint $table) {
            $table->id();
            $table->string('token')->index();
            $table->string('group')->index();
            $table->string('bucket')->index();
            $table->string('label')->nullable();
            $table->string('filename');
            $table->string('file_path');
            $table->string('file_disk')->default('local');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('status')->default('ready');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_exports');
    }
};
