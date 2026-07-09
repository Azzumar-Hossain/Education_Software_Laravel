<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Your existing New button
            Actions\CreateAction::make(),
            
            // --- NEW: Smart Print Button ---
            Actions\Action::make('print_report')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('success') // Makes the button green
                ->action(function () {
                    // 1. Get the data based on whatever filters the user has applied!
                    $records = $this->getFilteredTableQuery()->orderBy('date', 'asc')->get();

                    // 2. Separate them for the layout
                    $incomes = $records->where('type', 'Income');
                    $expenses = $records->where('type', 'Expense');

                    // 3. Calculate Totals
                    $totalIncome = $incomes->sum('amount');
                    $totalExpense = $expenses->sum('amount');
                    $net = $totalIncome - $totalExpense;

                    // 4. Generate the PDF
                    $pdf = Pdf::loadView('pdf.income-expense', [
                        'incomes' => $incomes,
                        'expenses' => $expenses,
                        'totalIncome' => $totalIncome,
                        'totalExpense' => $totalExpense,
                        'net' => $net,
                        'settings' => \App\Models\SiteSetting::first(),
                    ]);

                    // 5. Download the file
                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        'financial-report-' . date('d-M-Y') . '.pdf'
                    );
                }),
        ];
    }
}