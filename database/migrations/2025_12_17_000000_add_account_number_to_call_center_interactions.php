<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountNumberToCallCenterInteractions extends Migration
{
    public function up()
    {
        Schema::table('call_center_interactions', function (Blueprint $table) {
            $table->string('account_number')->nullable()->after('agent_id')->index();
        });
    }

    public function down()
    {
        Schema::table('call_center_interactions', function (Blueprint $table) {
            $table->dropIndex(['account_number']);
            $table->dropColumn('account_number');
        });
    }
}
