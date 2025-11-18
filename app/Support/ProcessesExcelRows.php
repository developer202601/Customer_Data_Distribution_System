<?php

namespace App\Support;

trait ProcessesExcelRows
{
    private const NEW_ARREARS_PREFIX = 'NEW_ARREARS_';
    private const DYNAMIC_EXPECTED_PREFIXES = [self::NEW_ARREARS_PREFIX];

    private function separateHeaderAndRows(array $rows): array
    {
        if (empty($rows)) {
            return [[], []];
        }

        $rowKeys = array_keys($rows);
        $headerKey = $rowKeys[0];
        $headerRow = $rows[$headerKey];
        unset($rows[$headerKey]);

        $headers = $this->prepareHeaders($headerRow);

        return [$headers, $rows];
    }

    private function prepareHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $letter => $value) {
            $label = trim((string) $value);
            $normalised = $this->normaliseHeaderName($label);

            if ($normalised === '') {
                $normalised = sprintf('COLUMN_%s', $letter);
            }

            $headers[$letter] = [
                'label' => $label !== '' ? $label : sprintf('Column %s', $letter),
                'normalised' => $normalised,
            ];
        }

        return $headers;
    }

    private function normaliseHeaderName(string $header): string
    {
        if ($header === '') {
            return '';
        }

        $normalised = strtoupper(trim($header));
        $normalised = preg_replace('/\s+/', '_', $normalised);
        $normalised = preg_replace('/[^A-Z0-9_]/', '_', $normalised);

        return preg_replace('/_+/', '_', $normalised);
    }

    private function rowHasData(array $columns): bool
    {
        foreach ($columns as $value) {
            if (! $this->isEmpty($value)) {
                return true;
            }
        }

        return false;
    }

    private function isEmpty($value): bool
    {
        if ($value === null) {
            return true;
        }

        return trim((string) $value) === '';
    }

    private function isValidLatestBill($value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if ($value === '-') {
            return true;
        }

        $numeric = str_replace([',', ' '], '', $value);

        return is_numeric($numeric);
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $letter => $meta) {
            $map[$meta['normalised']] = $letter;
        }

        return $map;
    }

    private function passesFilters(array $columns, array $headers): bool
    {
        $headerMap = $this->buildHeaderMap($headers);

        $medium = strtoupper($this->getColumnValue($columns, $headerMap, 'MEDIUM'));
        $filterMediumValues = $this->getClassConstantArray('FILTER_MEDIUM_VALUES');
        if (! in_array($medium, $filterMediumValues, true)) {
            return false;
        }

        $status = strtoupper($this->getColumnValue($columns, $headerMap, 'LATEST_PRODUCT_STATUS'));
        $filterStatus = $this->getClassConstantValue('FILTER_STATUS_VALUE', 'OK');
        if ($status !== $filterStatus) {
            return false;
        }

        $arrearsColumn = $this->findHeaderColumnByPrefix($headerMap, self::NEW_ARREARS_PREFIX);
        if ($arrearsColumn === null) {
            return false;
        }

        $value = $columns[$arrearsColumn] ?? '';
        $arrears = $this->parseNumeric($value);
        $minArrears = $this->getClassConstantValue('FILTER_MIN_ARREARS', 2400);
        if ($arrears <= $minArrears) {
            return false;
        }

        return true;
    }

    private function filterRows(array $rows, array $headers): array
    {
        $headerMap = $this->buildHeaderMap($headers);
        $results = [];

        foreach ($rows as $rowIndex => $columns) {
            if (! $this->rowHasData($columns)) {
                continue;
            }

            if ($this->passesFilters($columns, $headers)) {
                $results[$rowIndex] = $columns;
            }
        }

        return $results;
    }

    private function filterVipRows(array $rows, array $headerMap): array
    {
        $results = [];

        foreach ($rows as $rowIndex => $columns) {
            $creditClass = $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_NAME');

            if ($creditClass === '') {
                $creditClass = $this->getColumnValue($columns, $headerMap, 'CUSTOMER_SEGMENT');
            }

            if ($creditClass === '') {
                continue;
            }

            if ($this->isVipCreditClass($creditClass)) {
                $results[$rowIndex] = $columns;
            }
        }

        return $results;
    }

    private function isVipCreditClass(string $value): bool
    {
        $candidate = trim($value);

        if ($candidate === '') {
            return false;
        }

        return (bool) preg_match('/^VIP(\s*-\s*.+)?$/i', $candidate);
    }

    private function searchRows(array $rows, array $headerMap, string $term): array
    {
        $term = $this->normaliseSearchTerm($term);

        if ($term === '') {
            return $rows;
        }

        $targets = array_filter([
            $headerMap['CUSTOMER_REF'] ?? null,
            $headerMap['ACCOUNT_NUM'] ?? null,
            $headerMap['PRODUCT_LABEL'] ?? null,
        ]);

        if (empty($targets)) {
            return [];
        }

        $results = [];

        foreach ($rows as $rowIndex => $columns) {
            foreach ($targets as $columnLetter) {
                $value = isset($columns[$columnLetter]) ? trim((string) $columns[$columnLetter]) : '';

                if ($value === '') {
                    continue;
                }

                if ($this->valueContainsTerm($value, $term)) {
                    $results[$rowIndex] = $columns;
                    break;
                }
            }
        }

        return $results;
    }

    private function normaliseSearchTerm(string $term): string
    {
        $term = trim($term);

        if ($term === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($term);
        }

        return strtolower($term);
    }

    private function valueContainsTerm(string $value, string $term): bool
    {
        if ($term === '') {
            return true;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($value, $term) !== false;
        }

        return stripos($value, $term) !== false;
    }

    private function getColumnValue(array $row, array $headerMap, string $column): string
    {
        if (! isset($headerMap[$column])) {
            return '';
        }

        $value = $row[$headerMap[$column]] ?? '';

        return trim((string) $value);
    }

    private function parseNumeric(string $value): float
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $numeric = str_replace([',', ' '], '', $value);

        return is_numeric($numeric) ? (float) $numeric : 0.0;
    }

    private function findHeaderColumnByPrefix(array $headerMap, string $prefix): ?string
    {
        foreach ($headerMap as $normalised => $letter) {
            if (str_starts_with($normalised, $prefix)) {
                return $letter;
            }
        }

        return null;
    }

    private function isArrearsHeader(string $normalised): bool
    {
        return str_starts_with($normalised, self::NEW_ARREARS_PREFIX);
    }

    /**
     * Validate arrears cell contains only numeric amount characters (digits, optional decimal point,
     * commas and spaces allowed as thousand separators). Returns true if valid.
     */
    private function isValidArrears($value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        // Disallow a lone dash; treat that as invalid for arrears amount
        if ($value === '-') {
            return false;
        }

        // Remove common thousands separators and spaces
        $normalized = str_replace([',', ' '], '', $value);

        // Allow optional leading minus, digits, optional decimal point and digits
        return (bool) preg_match('/^-?\d+(?:\.\d+)?$/', $normalized);
    }

    private function isExpectedColumn(string $normalised): bool
    {
        $expected = $this->getClassConstantArray('EXPECTED_COLUMNS');
        if (in_array($normalised, $expected, true)) {
            return true;
        }

        foreach (self::DYNAMIC_EXPECTED_PREFIXES as $prefix) {
            if (str_starts_with($normalised, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read a class constant and return it as an array if present, otherwise empty array.
     */
    private function getClassConstantArray(string $name): array
    {
        $constRef = static::class . '::' . $name;
        if (defined($constRef)) {
            $val = constant($constRef);
            return is_array($val) ? $val : [];
        }

        return [];
    }

    /**
     * Read a class constant value if present, otherwise return the provided default.
     */
    private function getClassConstantValue(string $name, $default)
    {
        $constRef = static::class . '::' . $name;
        if (defined($constRef)) {
            return constant($constRef);
        }

        return $default;
    }
}
