<x-filament-panels::page>
    <div class="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">1. Select Class</h3>
        <div class="flex flex-wrap gap-3">
            @foreach(\App\Models\SchoolClass::all() as $class)
                <button
                    wire:click="setClass({{ $class->id }})"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                    {{ $activeClassId === $class->id
                        ? 'bg-indigo-600 text-white shadow-md hover:bg-indigo-500'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    {{ $class->name }}
                </button>
            @endforeach
        </div>
    </div>

    @if($activeClassId)
        @php
            // We ask Laravel to find the class and automatically pull its related sections!
            $selectedClass = \App\Models\SchoolClass::find($activeClassId);
            $sections = $selectedClass ? $selectedClass->sections : [];
        @endphp
        
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 mt-4">
            <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">2. Select Section</h3>
            <div class="flex flex-wrap gap-3">
                @foreach($sections as $section)
                    <button
                        wire:click="setSection({{ $section->id }})"
                        class="px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200
                        {{ $activeSectionId === $section->id
                            ? 'bg-emerald-600 text-white shadow-md hover:bg-emerald-500'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                    >
                        {{ $section->name }}
                    </button>
                @endforeach

                @if(count($sections) === 0)
                    <span class="text-sm text-red-500 font-medium py-2">No sections found for this class. Please attach one in the School Classes menu!</span>
                @endif
            </div>
        </div>
    @endif

    @if($activeClassId && $activeSectionId)
        <div class="mt-4">
            {{ $this->table }}
        </div>
    @else
        <div class="p-12 mt-4 text-center bg-white dark:bg-gray-900 rounded-xl ring-1 ring-dashed ring-gray-950/20 dark:ring-white/20">
            <div class="flex flex-col items-center justify-center">
                <x-heroicon-o-academic-cap class="w-16 h-16 text-gray-400 mb-4" />
                <h2 class="text-2xl font-bold text-gray-700 dark:text-gray-300">Awaiting Selection</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-2">Please click on a Class and then a Section above to start entering marks.</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>