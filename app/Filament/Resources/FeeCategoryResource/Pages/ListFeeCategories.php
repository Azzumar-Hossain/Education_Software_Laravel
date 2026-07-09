<?php

namespace App\Filament\Resources\FeeCategoryResource\Pages;

use App\Filament\Resources\FeeCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeeCategories extends ListRecords
{
    protected static string $resource = FeeCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
