<x-filament-panels::page>
    
    <form wire:submit.prevent="submit">
        {{ $this->form }}
    </form>

    @php 
        $report = $this->reportData; 
    @endphp

    <div class="grid gap-6">
        
        <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">Actual Cash Collected in Period</h2>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-success-600 dark:text-success-400">
                ৳ {{ number_format($report['grand_total'], 2) }}
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-950 dark:text-white">Fee Head (বিবরণ)</th>
                        <th class="px-6 py-4 text-sm font-semibold text-right text-gray-950 dark:text-white">Amount Allocated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse($report['rows'] as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75">
                            <td class="px-6 py-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $row['name'] }} 
                                @if($row['name_bn'])
                                    <span class="text-gray-400 font-normal">({{ $row['name_bn'] }})</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold {{ $row['amount'] < 0 ? 'text-danger-600' : 'text-primary-600 dark:text-primary-400' }}">
                                ৳ {{ number_format($row['amount'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-10 text-center text-gray-500">
                                No cash collections recorded within the selected date range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-white/5 border-t border-gray-200 dark:border-white/10">
                        <td class="px-6 py-4 text-sm font-bold text-right text-gray-900 dark:text-white">Net Total:</td>
                        <td class="px-6 py-4 text-sm font-bold text-right text-success-600 dark:text-success-400">
                            ৳ {{ number_format($report['grand_total'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    </div>
</x-filament-panels::page>