<?php

namespace App\Exports;

use App\Imports\AnimalImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AnimalTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return AnimalImport::DISPLAY_HEADINGS;
    }
}
