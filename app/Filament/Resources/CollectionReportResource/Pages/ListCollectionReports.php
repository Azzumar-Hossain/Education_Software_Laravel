<?php

namespace App\Filament\Resources\CollectionReportResource\Pages;

use App\Filament\Resources\CollectionReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;

class ListCollectionReports extends ListRecords
{
    protected static string $resource = CollectionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // --- NEW: Smart Print Button ---
            Actions\Action::make('print_report')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->action(function () {
                    // 1. Get the filtered data
                    $payments = $this->getFilteredTableQuery()->orderBy('payment_date', 'asc')->get();

                    // 2. Calculate Total Collected
                    $totalCollected = $payments->sum('amount_paid');

                    // 3. Generate the PDF
                    $pdf = Pdf::loadView('pdf.collection-report', [
                        'payments' => $payments,
                        'totalCollected' => $totalCollected,
                        'settings' => \App\Models\SiteSetting::first(),
                    ]);

                    // 4. Download the file
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        'collection-report-' . date('d-M-Y') . '.pdf'
                    );
                }),
        ];
    }
}