<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterDatasetProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'dataset_month',
        'arrears_date',
        'run_date',
        'run_date_raw',
        'master_archive_path',
        'master_workbook_path',
        'python_manifest_path',
        'python_status_path',
        'python_ran_at',
        'python_exit_code',
        'storage_disk',
        'master_filesize',
        'exclusion_archives',
        'user_id',
        'user_name',
        'row_count',
        'excluded_count',
        'call_center_staff_count',
        'call_center_count',
        'staff_count',
        'region_billing_count',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'arrears_date' => 'date',
        'run_date' => 'datetime',
        'exclusion_archives' => 'array',
        'master_filesize' => 'integer',
        'row_count' => 'integer',
        'excluded_count' => 'integer',
        'call_center_staff_count' => 'integer',
        'call_center_count' => 'integer',
        'staff_count' => 'integer',
        'region_billing_count' => 'integer',
        'python_ran_at' => 'datetime',
        'python_exit_code' => 'integer',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(MasterDatasetRow::class, 'process_id');
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('dataset_month', $month);
    }
}
