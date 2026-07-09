<?php

namespace App\Filament\Resources\MarkResource\Pages;

use App\Filament\Resources\MarkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarks extends ListRecords
{
    protected static string $resource = MarkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // We keep this empty because our "Generate" button is in the table itself
        ];
    }
}