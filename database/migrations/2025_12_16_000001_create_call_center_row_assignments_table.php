<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallCenterRowAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('call_center_row_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('call_center_report_id')->index();
            $table->unsignedBigInteger('master_dataset_row_id')->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->enum('status', ['pending', 'claimed', 'completed'])->default('pending')->index();
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by')->nullable();
            $table->timestamps();

            $table->foreign('call_center_report_id')
                ->references('id')->on('call_center_reports')
                ->onDelete('cascade');

            // master_dataset_rows exists in this project; keep referential integrity
            $table->foreign('master_dataset_row_id')
                ->references('id')->on('master_dataset_rows')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('call_center_row_assignments');
    }
}
