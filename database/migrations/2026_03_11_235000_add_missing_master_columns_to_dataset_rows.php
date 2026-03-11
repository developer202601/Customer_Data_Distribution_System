<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            $table->string('installment')->nullable()->after('account_num');
            $table->string('account_status')->nullable()->after('installment');
            $table->date('acct_effect_dtm')->nullable()->after('account_status');
            $table->string('bill_seq')->nullable()->after('acct_effect_dtm');

            $table->decimal('payments_value', 18, 2)->nullable()->after('new_arrears_column');
            $table->string('payments_column')->nullable()->after('payments_value');
            $table->decimal('new_arrears_secondary_value', 18, 2)->nullable()->after('payments_column');
            $table->string('new_arrears_secondary_column')->nullable()->after('new_arrears_secondary_value');

            $table->string('credit_class_id')->nullable()->after('credit_score');

            $table->date('payment_due_dat')->nullable()->after('next_bill_dtm');

            $table->string('product_name')->nullable()->after('product_id');
            $table->date('start_dat')->nullable()->after('product_name');
            $table->date('end_dat')->nullable()->after('start_dat');
            $table->date('latest_effective_dtm')->nullable()->after('latest_product_status');

            $table->string('phone_number')->nullable()->after('bill_handling_code');
        });

        Schema::table('master_dataset_rows_staging', function (Blueprint $table) {
            $table->string('installment')->nullable()->after('account_num');
            $table->string('account_status')->nullable()->after('installment');
            $table->date('acct_effect_dtm')->nullable()->after('account_status');
            $table->string('bill_seq')->nullable()->after('acct_effect_dtm');

            $table->decimal('payments_value', 18, 2)->nullable()->after('new_arrears_column');
            $table->string('payments_column')->nullable()->after('payments_value');
            $table->decimal('new_arrears_secondary_value', 18, 2)->nullable()->after('payments_column');
            $table->string('new_arrears_secondary_column')->nullable()->after('new_arrears_secondary_value');

            $table->string('credit_class_id')->nullable()->after('credit_score');

            $table->date('payment_due_dat')->nullable()->after('next_bill_dtm');

            $table->string('product_name')->nullable()->after('product_id');
            $table->date('start_dat')->nullable()->after('product_name');
            $table->date('end_dat')->nullable()->after('start_dat');
            $table->date('latest_effective_dtm')->nullable()->after('latest_product_status');

            $table->string('phone_number')->nullable()->after('bill_handling_code');
        });
    }

    public function down(): void
    {
        Schema::table('master_dataset_rows', function (Blueprint $table) {
            $table->dropColumn([
                'installment',
                'account_status',
                'acct_effect_dtm',
                'bill_seq',
                'payments_value',
                'payments_column',
                'new_arrears_secondary_value',
                'new_arrears_secondary_column',
                'credit_class_id',
                'payment_due_dat',
                'product_name',
                'start_dat',
                'end_dat',
                'latest_effective_dtm',
                'phone_number',
            ]);
        });

        Schema::table('master_dataset_rows_staging', function (Blueprint $table) {
            $table->dropColumn([
                'installment',
                'account_status',
                'acct_effect_dtm',
                'bill_seq',
                'payments_value',
                'payments_column',
                'new_arrears_secondary_value',
                'new_arrears_secondary_column',
                'credit_class_id',
                'payment_due_dat',
                'product_name',
                'start_dat',
                'end_dat',
                'latest_effective_dtm',
                'phone_number',
            ]);
        });
    }
};
