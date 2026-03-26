<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\DatasetExport;
use App\Models\User;

class MasterDatasetProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'latest_exclusion_token',
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

        'assignment_config_source',
        'assignment_config_overrides',
        'assignment_config_default_snapshot',
        'assignment_config_ftth_count',
        'assignment_config_set_by_user_id',
        'assignment_config_set_at',
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

        'assignment_config_overrides' => 'array',
        'assignment_config_default_snapshot' => 'array',
        'assignment_config_ftth_count' => 'integer',
        'assignment_config_set_by_user_id' => 'integer',
        'assignment_config_set_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(MasterDatasetRow::class, 'process_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(DatasetExport::class, 'token', 'token');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignmentConfigSetter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignment_config_set_by_user_id');
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('dataset_month', $month);
    }
}
