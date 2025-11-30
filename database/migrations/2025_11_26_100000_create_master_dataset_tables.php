<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_dataset_processes', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('dataset_month')->index();
            $table->date('arrears_date')->nullable()->index();
            $table->dateTime('run_date')->nullable();
            $table->string('run_date_raw')->nullable();
            $table->string('master_archive_path');
            $table->string('master_workbook_path');
            $table->string('storage_disk')->default('local');
            $table->unsignedBigInteger('master_filesize')->nullable();
            $table->json('exclusion_archives')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('excluded_count')->default(0);
            $table->unsignedInteger('call_center_staff_count')->default(0);
            $table->unsignedInteger('call_center_count')->default(0);
            $table->unsignedInteger('staff_count')->default(0);
            $table->unsignedInteger('region_billing_count')->default(0);
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('master_dataset_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('master_dataset_processes')->cascadeOnDelete();
            $table->dateTime('run_date')->nullable();
            $table->string('run_date_raw')->nullable();
            $table->string('region')->nullable();
            $table->string('rtom')->nullable();
            $table->string('customer_ref')->nullable();
            $table->string('account_num')->nullable()->index();
            $table->string('product_label')->nullable();
            $table->string('medium')->nullable();
            $table->string('customer_segment')->nullable();
            $table->string('address_name')->nullable();
            $table->text('full_address')->nullable();
            $table->decimal('latest_bill_mny', 18, 2)->nullable();
            $table->decimal('new_arrears_value', 18, 2)->nullable();
            $table->string('new_arrears_column')->nullable();
            $table->string('mobile_contact_tel')->nullable();
            $table->string('email_address')->nullable();
            $table->decimal('credit_score', 10, 2)->nullable();
            $table->string('credit_class_name')->nullable();
            $table->string('bill_handling_code_name')->nullable();
            $table->unsignedInteger('age_months')->nullable();
            $table->string('sales_person')->nullable();
            $table->string('account_manager')->nullable();
            $table->string('slt_gl_sub_segment')->nullable();
            $table->string('billing_centre')->nullable();
            $table->string('province')->nullable();
            $table->date('next_bill_dtm')->nullable();
            $table->string('bill_month')->nullable();
            $table->date('latest_bill_dtm')->nullable();
            $table->string('invoicing_co_id')->nullable();
            $table->string('invoicing_co_name')->nullable();
            $table->string('product_seq')->nullable();
            $table->string('product_id')->nullable();
            $table->string('latest_product_status')->nullable();
            $table->string('bill_handling_code')->nullable();
            $table->string('slt_business_line_value')->nullable();
            $table->string('sales_channel')->nullable();
            $table->boolean('excluded')->default(false)->index();
            $table->string('exclusion_reason')->nullable();
            $table->string('assigned_to')->nullable()->index();
            $table->unsignedInteger('exclusion_priority')->default(0);
            $table->timestamps();

            $table->unique(['process_id', 'run_date_raw', 'product_label'], 'mdr_process_run_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_dataset_rows');
        Schema::dropIfExists('master_dataset_processes');
    }
};
