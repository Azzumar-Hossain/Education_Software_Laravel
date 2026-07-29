<x-filament-panels::page>
    <x-filament-panels::form wire:submit="generateStudentList">
        {{ $this->form }}

        <!-- PART 2: Generate Action Button inside Form Card -->
        <div class="mt-4 flex justify-end">
            <x-filament::button 
                type="submit" 
                icon="heroicon-o-sparkles" 
                color="warning" 
                size="lg">
                Generate Marksheet List
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    <!-- PART 3: Top-Right Print Button & Student Records List -->
    @if(count($studentsList) > 0)
        <div class="mt-8 bg-white dark:bg-gray-900 shadow rounded-xl p-6 border border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="text-lg font-bold text-gray-800 dark:text-gray-200">
                    Ready for Print: <span class="text-amber-500">{{ count($studentsList) }} Students</span>
                </div>

                <x-filament::button 
                    wire:click="downloadBatchMarksheets" 
                    icon="heroicon-o-printer" 
                    color="warning" 
                    size="lg">
                    Print Batch Marksheets
                </x-filament::button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="p-3">Roll</th>
                            <th class="p-3">Student ID</th>
                            <th class="p-3">Student Name</th>
                            <th class="p-3">Section</th>
                            <th class="p-3">Group</th>
                            <th class="p-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach($studentsList as $student)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="p-3 font-semibold text-gray-900 dark:text-gray-100">{{ $student['roll_number'] }}</td>
                                <td class="p-3">{{ $student['student_id'] }}</td>
                                <td class="p-3 font-bold text-gray-800 dark:text-gray-200">{{ $student['name'] }}</td>
                                <td class="p-3">{{ $student['section'] }}</td>
                                <td class="p-3">{{ $student['group'] }}</td>
                                <td class="p-3 text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        Marks Ready
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>