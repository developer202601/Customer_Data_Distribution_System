<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterDatasetRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_id',
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
        'assigned_to',
        'exclusion_priority',
    ];

    protected $casts = [
        'run_date' => 'datetime',
        'acct_effect_dtm' => 'date',
        'latest_bill_mny' => 'decimal:2',
        'new_arrears_value' => 'decimal:2',
        'payments_value' => 'decimal:2',
        'new_arrears_secondary_value' => 'decimal:2',
        'credit_score' => 'decimal:2',
        'age_months' => 'integer',
        'next_bill_dtm' => 'date',
        'payment_due_dat' => 'date',
        'latest_bill_dtm' => 'date',
        'start_dat' => 'date',
        'end_dat' => 'date',
        'latest_effective_dtm' => 'date',
        'excluded' => 'boolean',
        'exclusion_priority' => 'integer',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(MasterDatasetProcess::class, 'process_id');
    }
}
