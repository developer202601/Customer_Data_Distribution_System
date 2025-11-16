<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ChunkReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $startRow,
        private readonly int $endRow,
        private readonly bool $alwaysIncludeHeader = true
    ) {
    }

    public function readCell($column, $row, $worksheetName = ''): bool
    {
        if ($this->alwaysIncludeHeader && $row === 1) {
            return true;
        }

        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
