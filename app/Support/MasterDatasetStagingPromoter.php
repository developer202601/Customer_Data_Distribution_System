<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use Illuminate\Support\Facades\DB;

class MasterDatasetStagingPromoter
{
    /**
     * Promote rows from the staging table into the main dataset rows table.
     */
    public function promote(MasterDatasetProcess $process): array
    {
        $rowCount = DB::table('master_dataset_rows_staging')
            ->where('process_id', $process->id)
            ->count();

        if ($rowCount === 0) {
            return ['promoted' => 0];
        }

        $columns = $this->columnList();

        DB::transaction(function () use ($process, $columns) {
            DB::table('master_dataset_rows_staging')
                ->where('process_id', $process->id)
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($process, $columns) {
                    $payload = [];
                    $ids = [];
                    $now = now();

                    foreach ($rows as $row) {
                        $ids[] = $row->id;
                        $record = ['process_id' => $process->id];

                        foreach ($columns as $column) {
                            $record[$column] = $row->{$column} ?? null;
                        }

                        $record['created_at'] = $now;
                        $record['updated_at'] = $now;
                        $payload[] = $record;
                    }

                    if (! empty($payload)) {
                        MasterDatasetRow::insert($payload);
                    }

                    DB::table('master_dataset_rows_staging')->whereIn('id', $ids)->delete();
                });
        });

        return ['promoted' => $rowCount];
    }

    private function columnList(): array
    {
        return [
            'run_date',
            'run_date_raw',
            'region',
            'rtom',
            'customer_ref',
            'account_num',
            'installment',
            'account_status',
            'acct_effect_dtm',
            'bill_seq',
            'product_label',
            'medium',
            'customer_segment',
            'address_name',
            'full_address',
            'latest_bill_mny',
            'new_arrears_value',
            'new_arrears_column',
            'payments_value',
            'payments_column',
            'new_arrears_secondary_value',
            'new_arrears_secondary_column',
            'mobile_contact_tel',
            'email_address',
            'credit_score',
            'credit_class_id',
            'credit_class_name',
            'bill_handling_code_name',
            'age_months',
            'sales_person',
            'account_manager',
            'slt_gl_sub_segment',
            'billing_centre',
            'province',
            'next_bill_dtm',
            'payment_due_dat',
            'bill_month',
            'latest_bill_dtm',
            'invoicing_co_id',
            'invoicing_co_name',
            'product_seq',
            'product_id',
            'product_name',
            'start_dat',
            'end_dat',
            'latest_product_status',
            'latest_effective_dtm',
            'bill_handling_code',
            'phone_number',
            'slt_business_line_value',
            'sales_channel',
            'excluded',
            'exclusion_reason',
            'exclusion_priority',
            'assigned_to',
        ];
    }
}
