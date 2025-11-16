<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FileDataImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        // This class will simply collect all rows from the Excel file.
        // The main controller will handle the processing of these rows.
    }
}
