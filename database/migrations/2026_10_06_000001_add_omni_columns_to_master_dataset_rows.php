<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOmniColumnsToMasterDatasetRows extends Migration
{
    public function up()
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            if (! Schema::hasColumn('master_dataset_rows', 'last_payment_amount')) {
                $table->decimal('last_payment_amount', 12, 2)->nullable()->after('payments_value');
            }
            if (! Schema::hasColumn('master_dataset_rows', 'last_payment_date')) {
                $table->date('last_payment_date')->nullable()->after('last_payment_amount');
            }
            if (! Schema::hasColumn('master_dataset_rows', 'outstanding_balance')) {
                $table->decimal('outstanding_balance', 12, 2)->nullable()->after('last_payment_date');
            }
            if (! Schema::hasColumn('master_dataset_rows', 'last_api_fetched')) {
                $table->timestamp('last_api_fetched')->nullable()->after('outstanding_balance');
            }
        });
    }

    public function down()
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            if (Schema::hasColumn('master_dataset_rows', 'last_api_fetched')) {
                $table->dropColumn('last_api_fetched');
            }
            if (Schema::hasColumn('master_dataset_rows', 'outstanding_balance')) {
                $table->dropColumn('outstanding_balance');
            }
            if (Schema::hasColumn('master_dataset_rows', 'last_payment_date')) {
                $table->dropColumn('last_payment_date');
            }
            if (Schema::hasColumn('master_dataset_rows', 'last_payment_amount')) {
                $table->dropColumn('last_payment_amount');
            }
        });
    }
}