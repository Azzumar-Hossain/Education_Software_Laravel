<div class="space-y-4">
    @php
        $livewire = $getLivewire();
        $currentLogo = $getRecord()?->logo ?? ($data['logo'] ?? null);
    @endphp

    <div class="flex items-center gap-6 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50">
        <!-- Image Preview Box -->
        <div class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center bg-white dark:bg-gray-800 overflow-hidden shadow-sm shrink-0">
            @if ($livewire->newLogo)
                <img src="{{ $livewire->newLogo->temporaryUrl() }}" class="w-full h-full object-contain p-1">
            @elseif($currentLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($currentLogo) }}" class="w-full h-full object-contain p-1">
            @else
                <span class="text-xs text-gray-400 font-medium">No Logo</span>
            @endif
        </div>

        <!-- Dedicated Upload Button Container -->
        <div class="space-y-2">
            <label for="custom-logo-input" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-500 text-white font-bold text-xs rounded-lg cursor-pointer shadow-sm transition duration-150 ease-in-out">
                <x-heroicon-m-arrow-up-tray class="w-4 h-4" />
                <span>Select & Upload New Logo</span>
            </label>

            <!-- Hidden Native Input Triggered by Button -->
            <input 
                type="file" 
                id="custom-logo-input" 
                wire:model.live="newLogo" 
                accept="image/png,image/jpeg,image/jpg,image/webp"
                class="hidden"
            >

            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                @if($livewire->newLogo)
                    <span class="text-success-600 dark:text-success-400 font-bold">
                        ✓ Selected: {{ $livewire->newLogo->getClientOriginalName() }}
                    </span>
                @else
                    <span>Supported Formats: PNG, JPG, WEBP (Max: 2MB)</span>
                @endif
            </div>
        </div>
    </div>
</div>