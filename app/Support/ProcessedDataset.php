<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ProcessedDataset
{
    use ProcessesExcelRows;

    private string $token;
    private string $manifestPath;
    private array $manifest;
    private ?array $headerMap = null;

    public function __construct(string $token, string $manifestPath)
    {
        $this->token = $token;
        $this->manifestPath = $manifestPath;
        $this->manifest = $this->loadManifest();
    }

    public static function fromSession(): ?self
    {
        $data = session('process.upload.dataset');

        if (! is_array($data)) {
            return null;
        }

        $token = $data['token'] ?? null;
        $manifestPath = $data['manifest_path'] ?? null;

        if (! is_string($token) || ! is_string($manifestPath)) {
            return null;
        }

        return new self($token, $manifestPath);
    }

    public function token(): string
    {
        return $this->token;
    }

    public function manifestPath(): string
    {
        return $this->manifestPath;
    }

    public function headers(): array
    {
        return $this->manifest['headers'] ?? [];
    }

    public function headerMap(): array
    {
        if ($this->headerMap === null) {
            $this->headerMap = $this->buildHeaderMap($this->headers());
        }

        return $this->headerMap;
    }

    public function originalFilename(): ?string
    {
        $name = $this->manifest['original_filename'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }

    public function sourceRowCount(): int
    {
        return (int) ($this->manifest['source_total_rows'] ?? $this->manifest['total_rows'] ?? 0);
    }

    public function filteredRowCount(): int
    {
        return (int) ($this->manifest['filtered_rows_total'] ?? 0);
    }

    public function filteredOutCount(): int
    {
        return (int) ($this->manifest['filtered_out_total'] ?? 0);
    }

    public function vipRowCount(): int
    {
        return (int) ($this->manifest['vip_rows_total'] ?? 0);
    }

    public function chunkMetadata(): array
    {
        return $this->manifest['chunks'] ?? [];
    }

    public function paginateFilteredRows(int $page, int $perPage, bool $vip, string $searchTerm): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $searchTerm = trim($searchTerm);

        $offset = ($page - 1) * $perPage;

        if ($searchTerm === '') {
            if (! $vip) {
                return $this->collectPlainPage($offset, $perPage);
            }

            return $this->collectVipPage($offset, $perPage);
        }

        return $this->collectFilteredPage($offset, $perPage, $vip, $searchTerm);
    }

    public function firstFilteredRows(int $limit, bool $vip, string $searchTerm = ''): array
    {
        $limit = max(1, $limit);
        $result = $this->paginateFilteredRows(1, $limit, $vip, $searchTerm);
        $rows = $result['rows'];
        $total = (int) $result['total'];

        return [
            'rows' => $rows,
            'total' => $total,
            'limited' => $total > count($rows),
        ];
    }

    public function filteredOutListing(int $limit, string $searchTerm = ''): array
    {
        $limit = max(1, $limit);
        $searchTerm = $this->normaliseSearchTerm($searchTerm);

        $headerMap = $this->headerMap();
        $results = [];
        $total = 0;

        foreach ($this->filteredOutGenerator() as $rowIndex => $entry) {
            if ($searchTerm !== '' && ! $this->filteredOutMatchesSearch($entry, $headerMap, $searchTerm)) {
                continue;
            }

            if (count($results) < $limit) {
                $results[$rowIndex] = $entry;
            }

            $total++;
        }

        return [
            'rows' => $results,
            'total' => $total,
            'limited' => $total > $limit,
        ];
    }

    public function filteredOutReasonCounts(): array
    {
        $counts = $this->manifest['filtered_out_reason_counts'] ?? [];

        if (! empty($counts)) {
            return $counts;
        }

        $counts = [];

        foreach ($this->filteredOutGenerator() as $entry) {
            $reason = $entry['reason'] ?? 'Filtered out by eligibility rules.';
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        arsort($counts);
        $this->manifest['filtered_out_reason_counts'] = $counts;
        $this->persistManifest();

        return $counts;
    }

    public function filteredOutPreview(): array
    {
        $preview = $this->manifest['filtered_out_preview'] ?? null;

        if (is_array($preview) && ! empty($preview)) {
            return $preview;
        }

        $previewLimit = 10;
        $preview = [];

        foreach ($this->filteredOutGenerator() as $rowIndex => $entry) {
            if (count($preview) >= $previewLimit) {
                break;
            }

            $preview[$rowIndex] = $entry;
        }

        $this->manifest['filtered_out_preview'] = $preview;
        $this->persistManifest();

        return $preview;
    }

    public function filteredRowsGenerator(): \Generator
    {
        foreach ($this->chunkMetadata() as $chunk) {
            $payload = $this->readChunk($chunk);
            $rows = $payload['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            ksort($rows, SORT_NUMERIC);

            foreach ($rows as $rowIndex => $columns) {
                yield $rowIndex => $columns;
            }
        }
    }

    public function filteredRowsMatching(bool $vip, string $searchTerm = ''): \Generator
    {
        $headerMap = $this->headerMap();
        $term = $this->normaliseSearchTerm($searchTerm);

        foreach ($this->filteredRowsGenerator() as $rowIndex => $columns) {
            if ($vip && ! $this->rowIsVip($columns, $headerMap)) {
                continue;
            }

            if ($term !== '' && ! $this->rowMatchesSearch($columns, $headerMap, $term)) {
                continue;
            }

            yield $rowIndex => $columns;
        }
    }

    public function filteredOutGenerator(): \Generator
    {
        foreach ($this->chunkMetadata() as $chunk) {
            $payload = $this->readChunk($chunk);
            $entries = $payload['skipped'] ?? [];

            if (empty($entries)) {
                continue;
            }

            ksort($entries, SORT_NUMERIC);

            foreach ($entries as $rowIndex => $entry) {
                yield $rowIndex => $entry;
            }
        }
    }

    public function getFilteredRow(int $rowIndex): ?array
    {
        foreach ($this->chunkMetadata() as $chunk) {
            $start = (int) ($chunk['start_row'] ?? 0);
            $end = (int) ($chunk['end_row'] ?? 0);

            if ($start !== 0 && $end !== 0 && ($rowIndex < $start || $rowIndex > $end)) {
                continue;
            }

            $payload = $this->readChunk($chunk);
            $rows = $payload['rows'] ?? [];

            if (isset($rows[$rowIndex])) {
                return $rows[$rowIndex];
            }
        }

        return null;
    }

    public function removeAccounts(array $accountMap, array $detailMap): array
    {
        if (empty($accountMap)) {
            return [
                'removed' => [],
                'removed_count' => 0,
                'removed_vip' => 0,
            ];
        }

        $headerMap = $this->headerMap();
        $removed = [];
        $removedVip = 0;

        foreach ($this->manifest['chunks'] ?? [] as $index => $chunk) {
            $payload = $this->readChunk($chunk);
            $rows = $payload['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            $changed = false;
            $vipAdjust = 0;

            foreach ($rows as $rowIndex => $columns) {
                if (! is_array($columns)) {
                    continue;
                }

                $account = $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM');

                if ($account === '' || ! isset($accountMap[$account])) {
                    continue;
                }

                $detail = $detailMap[$account][0] ?? [];
                $removed[] = [
                    'row_index' => $rowIndex,
                    'account_num' => $account,
                    'customer_ref' => $this->getColumnValue($columns, $headerMap, 'CUSTOMER_REF'),
                    'columns' => $columns,
                    'reason' => $detail['reason'] ?? ('Excluded via ' . ($detail['file'] ?? 'exclusion file')),
                    'file' => $detail['file'] ?? null,
                ];

                if ($this->rowIsVip($columns, $headerMap)) {
                    $removedVip++;
                    $vipAdjust++;
                }

                unset($rows[$rowIndex]);
                $changed = true;
            }

            if ($changed) {
                $payload['rows'] = $rows;
                Storage::put($chunk['path'], json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $filteredCount = count($rows);
                $this->manifest['chunks'][$index]['filtered_count'] = $filteredCount;

                $currentVip = (int) ($this->manifest['chunks'][$index]['vip_count'] ?? 0);
                $this->manifest['chunks'][$index]['vip_count'] = max(0, $currentVip - $vipAdjust);
            }
        }

        $removedCount = count($removed);

        if ($removedCount > 0) {
            $this->manifest['filtered_rows_total'] = max(0, (int) ($this->manifest['filtered_rows_total'] ?? 0) - $removedCount);

            if (isset($this->manifest['vip_rows_total'])) {
                $this->manifest['vip_rows_total'] = max(0, (int) $this->manifest['vip_rows_total'] - $removedVip);
            }

            $this->manifest['updated_at'] = now()->toIso8601String();
            $this->persistManifest();
        }

        return [
            'removed' => $removed,
            'removed_count' => $removedCount,
            'removed_vip' => $removedVip,
        ];
    }

    public function exclusionHistory(): array
    {
        return $this->manifest['exclusions_history'] ?? [];
    }

    public function setExclusionHistory(array $history): void
    {
        $this->manifest['exclusions_history'] = $history;
        $this->manifest['updated_at'] = now()->toIso8601String();
        $this->persistManifest();
    }

    public function setExclusionRecords(array $records): void
    {
        $this->manifest['exclusion_records'] = $records;
        $this->manifest['updated_at'] = now()->toIso8601String();
        $this->persistManifest();
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function persistManifest(): void
    {
        Storage::put($this->manifestPath, json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function collectPlainPage(int $offset, int $perPage): array
    {
        $rowsOut = [];
        $remainingOffset = $offset;

        foreach ($this->chunkMetadata() as $chunk) {
            $filteredCount = (int) ($chunk['filtered_count'] ?? 0);

            if ($filteredCount === 0) {
                continue;
            }

            if ($remainingOffset >= $filteredCount) {
                $remainingOffset -= $filteredCount;
                continue;
            }

            $payload = $this->readChunk($chunk);
            $rows = $payload['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            ksort($rows, SORT_NUMERIC);

            if ($remainingOffset > 0) {
                $rows = array_slice($rows, $remainingOffset, null, true);
                $remainingOffset = 0;
            }

            foreach ($rows as $rowIndex => $columns) {
                if (count($rowsOut) >= $perPage) {
                    break 2;
                }

                $rowsOut[$rowIndex] = $columns;
            }
        }

        return [
            'rows' => $rowsOut,
            'total' => $this->filteredRowCount(),
        ];
    }

    private function collectVipPage(int $offset, int $perPage): array
    {
        $rowsOut = [];
        $remainingOffset = $offset;
        $headerMap = $this->headerMap();

        foreach ($this->chunkMetadata() as $chunk) {
            $vipCount = (int) ($chunk['vip_count'] ?? 0);

            if ($vipCount === 0) {
                continue;
            }

            if ($remainingOffset >= $vipCount) {
                $remainingOffset -= $vipCount;
                continue;
            }

            $payload = $this->readChunk($chunk);
            $rows = $payload['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            ksort($rows, SORT_NUMERIC);

            foreach ($rows as $rowIndex => $columns) {
                if (! $this->rowIsVip($columns, $headerMap)) {
                    continue;
                }

                if ($remainingOffset > 0) {
                    $remainingOffset--;
                    continue;
                }

                if (count($rowsOut) >= $perPage) {
                    break 2;
                }

                $rowsOut[$rowIndex] = $columns;
            }
        }

        return [
            'rows' => $rowsOut,
            'total' => $this->vipRowCount(),
        ];
    }

    private function collectFilteredPage(int $offset, int $perPage, bool $vip, string $searchTerm): array
    {
        $rowsOut = [];
        $total = 0;
        $skip = $offset;

        $headerMap = $this->headerMap();
        $term = $this->normaliseSearchTerm($searchTerm);

        foreach ($this->filteredRowsGenerator() as $rowIndex => $columns) {
            if ($vip && ! $this->rowIsVip($columns, $headerMap)) {
                continue;
            }

            if ($term !== '' && ! $this->rowMatchesSearch($columns, $headerMap, $term)) {
                continue;
            }

            if ($skip > 0) {
                $skip--;
                $total++;
                continue;
            }

            if (count($rowsOut) < $perPage) {
                $rowsOut[$rowIndex] = $columns;
            }

            $total++;
        }

        return [
            'rows' => $rowsOut,
            'total' => $total,
        ];
    }

    private function rowIsVip(array $columns, array $headerMap): bool
    {
        $creditClass = $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_NAME');

        if ($creditClass === '') {
            $creditClass = $this->getColumnValue($columns, $headerMap, 'CUSTOMER_SEGMENT');
        }

        if ($creditClass === '') {
            return false;
        }

        return $this->isVipCreditClass($creditClass);
    }

    private function rowMatchesSearch(array $columns, array $headerMap, string $term): bool
    {
        $targets = array_filter([
            $headerMap['CUSTOMER_REF'] ?? null,
            $headerMap['ACCOUNT_NUM'] ?? null,
            $headerMap['PRODUCT_LABEL'] ?? null,
        ]);

        foreach ($targets as $letter) {
            $value = isset($columns[$letter]) ? trim((string) $columns[$letter]) : '';

            if ($value !== '' && $this->valueContainsTerm($value, $term)) {
                return true;
            }
        }

        return false;
    }

    private function filteredOutMatchesSearch(array $entry, array $headerMap, string $term): bool
    {
        $reason = trim((string) ($entry['reason'] ?? ''));

        if ($reason !== '' && $this->valueContainsTerm($reason, $term)) {
            return true;
        }

        $columns = $entry['columns'] ?? [];

        if (! is_array($columns) || empty($columns)) {
            return false;
        }

        $targets = array_filter([
            $headerMap['CUSTOMER_REF'] ?? null,
            $headerMap['ACCOUNT_NUM'] ?? null,
            $headerMap['PRODUCT_LABEL'] ?? null,
        ]);

        foreach ($targets as $letter) {
            $value = isset($columns[$letter]) ? trim((string) $columns[$letter]) : '';

            if ($value !== '' && $this->valueContainsTerm($value, $term)) {
                return true;
            }
        }

        return false;
    }

    private function readChunk(array $chunk): array
    {
        $path = $chunk['path'] ?? null;

        if (! is_string($path) || $path === '') {
            return [];
        }

        if (! Storage::exists($path)) {
            return [];
        }

        $contents = Storage::get($path);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function loadManifest(): array
    {
        if (! Storage::exists($this->manifestPath)) {
            throw new \RuntimeException('Processed dataset manifest not found.');
        }

        $contents = Storage::get($this->manifestPath);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Processed dataset manifest is invalid.');
        }

        return $decoded['manifest'] ?? $decoded;
    }
}
