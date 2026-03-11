<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('master_dataset_rows', function (Blueprint $table) {
            $table->dropForeign(['process_id']);
            $table->dropUnique('mdr_process_run_product_unique');
            $table->unique([
                'process_id',
                'run_date_raw',
                'product_label',
                'account_num',
            ], 'mdr_process_run_product_unique');
            $table->foreign('process_id')
                ->references('id')
                ->on('master_dataset_processes')
                ->cascadeOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('master_dataset_rows', function (Blueprint $table) {
            $table->dropForeign(['process_id']);
            $table->dropUnique('mdr_process_run_product_unique');
            $table->unique([
                'process_id',
                'run_date_raw',
                'account_num',
            ], 'mdr_process_run_product_unique');
            $table->foreign('process_id')
                ->references('id')
                ->on('master_dataset_processes')
                ->cascadeOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }
};
