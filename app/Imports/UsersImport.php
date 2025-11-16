<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UsersImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    public function collection(Collection $rows)
    {
        // We are processing the file in chunks, but the logic is in the controller.
        // This class is now just for reading the file efficiently.
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
