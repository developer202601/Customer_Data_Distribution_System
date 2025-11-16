<?php

namespace App\Support;

trait ProcessesExcelRows
{
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
        if (! in_array($medium, static::FILTER_MEDIUM_VALUES, true)) {
            return false;
        }

        $status = strtoupper($this->getColumnValue($columns, $headerMap, 'LATEST_PRODUCT_STATUS'));
        if ($status !== static::FILTER_STATUS_VALUE) {
            return false;
        }

        $arrears = $this->parseNumeric($this->getColumnValue($columns, $headerMap, 'NEW_ARREARS_20251022'));
        if ($arrears <= static::FILTER_MIN_ARREARS) {
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
}
