<?php

namespace App\Filament\Resources\ReceiptResource\Pages;

use App\Filament\Resources\ReceiptResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use App\Models\Enrollment;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\FeeCategory;

class ListReceipts extends ListRecords
{
    protected static string $resource = ReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(), // Your existing create button

            // --- NEW BULK FEE GENERATOR ---
            
        ];
    }
}