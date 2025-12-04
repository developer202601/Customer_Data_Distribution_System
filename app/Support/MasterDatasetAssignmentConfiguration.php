<?php

namespace App\Support;

use App\Models\Configurations;

class MasterDatasetAssignmentConfiguration
{
    private const DEFAULT_VALUES = [
        'upper_range' => 10000,
        'lower_range' => 3000,
        'ccs' => 30000,
        'cc' => 5000,
        's' => 3000,
    ];

    private array $values;

    public function __construct()
    {
        $this->values = $this->loadValuesFromDatabase();
    }

    public function getRetailUpperBound(): int
    {
        return $this->values['upper_range'];
    }

    public function getRetailLowerBound(): int
    {
        return $this->values['lower_range'];
    }

    public function getCallCenterStaffQuota(): int
    {
        return $this->values['ccs'];
    }

    public function getCallCenterQuota(): int
    {
        return $this->values['cc'];
    }

    public function getStaffQuota(): int
    {
        return $this->values['s'];
    }

    public function getTotalRetailSelectionGoal(): int
    {
        return $this->getCallCenterStaffQuota() + $this->getCallCenterQuota() + $this->getStaffQuota();
    }

    public function toArray(): array
    {
        return [
            'upper_range' => $this->getRetailUpperBound(),
            'lower_range' => $this->getRetailLowerBound(),
            'call_center_staff_quota' => $this->getCallCenterStaffQuota(),
            'call_center_quota' => $this->getCallCenterQuota(),
            'staff_quota' => $this->getStaffQuota(),
        ];
    }

    private function loadValuesFromDatabase(): array
    {
        $records = Configurations::query()
            ->whereIn('config_name', array_keys(self::DEFAULT_VALUES))
            ->get()
            ->keyBy('config_name');

        $values = [];

        foreach (self::DEFAULT_VALUES as $key => $default) {
            $record = $records->get($key);
            $values[$key] = $record ? $this->normalizeValue($record->value) : $default;
        }

        return $values;
    }

    private function normalizeValue(mixed $value): int
    {
        $numeric = (int) $value;
        return $numeric < 0 ? 0 : $numeric;
    }
}