<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallCenterInteractionsTable extends Migration
{
    public function up()
    {
        Schema::create('call_center_interactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('assignment_id')->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->string('outcome', 100)->nullable();
            $table->text('note')->nullable();
            $table->date('payment_expected_at')->nullable();
            $table->boolean('paid')->default(false)->index();
            $table->date('payment_date')->nullable();
            $table->timestamps();

            $table->foreign('assignment_id')
                ->references('id')->on('call_center_row_assignments')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('call_center_interactions');
    }
}
