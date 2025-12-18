<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentColumnsToCallCenterRowAssignments extends Migration
{
    public function up()
    {
        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('call_center_row_assignments', 'paid')) {
                $table->boolean('paid')->default(false)->after('status')->index();
            }
            if (! Schema::hasColumn('call_center_row_assignments', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('paid');
            }
            if (! Schema::hasColumn('call_center_row_assignments', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->nullable()->after('payment_date');
            }
        });
    }

    public function down()
    {
        Schema::table('call_center_row_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('call_center_row_assignments', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('call_center_row_assignments', 'payment_date')) {
                $table->dropColumn('payment_date');
            }
            if (Schema::hasColumn('call_center_row_assignments', 'paid')) {
                $table->dropColumn('paid');
            }
        });
    }
}
