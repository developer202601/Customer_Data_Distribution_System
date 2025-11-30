<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterDatasetRowStaging extends Model
{
    use HasFactory;

    protected $table = 'master_dataset_rows_staging';

    protected $fillable = [
        'process_id',
        'payload',
        'run_date_raw',
        'run_date',
        'region',
        'rtom',
        'customer_ref',
        'account_num',
        'product_label',
        'medium',
        'customer_segment',
        'address_name',
        'full_address',
        'latest_bill_mny',
        'new_arrears_value',
        'new_arrears_column',
        'mobile_contact_tel',
        'email_address',
        'credit_score',
        'credit_class_name',
        'bill_handling_code_name',
        'age_months',
        'sales_person',
        'account_manager',
        'slt_gl_sub_segment',
        'billing_centre',
        'province',
        'next_bill_dtm',
        'bill_month',
        'latest_bill_dtm',
        'invoicing_co_id',
        'invoicing_co_name',
        'product_seq',
        'product_id',
        'latest_product_status',
        'bill_handling_code',
        'slt_business_line_value',
        'sales_channel',
        'excluded',
        'exclusion_reason',
        'exclusion_priority',
        'assigned_to',
    ];

    protected $casts = [
        'payload' => 'array',
        'run_date' => 'datetime',
        'next_bill_dtm' => 'date',
        'latest_bill_dtm' => 'date',
        'latest_bill_mny' => 'float',
        'new_arrears_value' => 'float',
        'credit_score' => 'float',
        'excluded' => 'boolean',
    ];
}
