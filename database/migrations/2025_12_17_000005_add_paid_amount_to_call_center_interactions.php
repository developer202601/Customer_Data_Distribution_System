<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaidAmountToCallCenterInteractions extends Migration
{
    public function up()
    {
        Schema::table('call_center_interactions', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_interactions', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->nullable()->after('payment_date');
            }
        });
    }

    public function down()
    {
        Schema::table('call_center_interactions', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_interactions', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
        });
    }
}
