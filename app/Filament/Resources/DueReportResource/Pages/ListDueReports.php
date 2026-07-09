<?php

namespace App\Filament\Resources\DueReportResource\Pages;

use App\Filament\Resources\DueReportResource;
use Filament\Resources\Pages\ListRecords;

class ListDueReports extends ListRecords
{
    protected static string $resource = DueReportResource::class;

    protected function getHeaderActions(): array
    {
        // Return an empty array so the "New" button doesn't show up on the report page
        return [];
    }
}