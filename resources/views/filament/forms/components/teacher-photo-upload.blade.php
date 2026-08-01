<div class="space-y-4">
    @php
        $livewire = $getLivewire();
        $record = $getRecord();
        
        // Resolve current teacher photo path
        $currentPhoto = $record?->avatar ?? ($record?->profile_photo_path ?? ($data['avatar'] ?? ($data['profile_photo_path'] ?? null)));
    @endphp

    <div class="flex items-center gap-6 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50" style="max-width: 500px !important;">
        
        <!-- 🌟 HARD-LOCKED 100px x 100px CIRCULAR CONTAINER 🌟 -->
        <div style="width: 100px !important; height: 100px !important; min-width: 100px !important; min-height: 100px !important; max-width: 100px !important; max-height: 100px !important; border-radius: 9999px !important; overflow: hidden !important;" class="border-2 border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-center shrink-0 shadow-sm relative">
            
            @if ($livewire->newPhoto)
                <img src="{{ $livewire->newPhoto->temporaryUrl() }}" style="width: 100% !important; height: 100% !important; max-width: 100% !important; max-height: 100% !important; object-fit: cover !important; border-radius: 9999px !important;">
            @elseif($currentPhoto)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($currentPhoto) }}" style="width: 100% !important; height: 100% !important; max-width: 100% !important; max-height: 100% !important; object-fit: cover !important; border-radius: 9999px !important;">
            @else
                <div class="text-center p-2">
                    <x-heroicon-o-user class="w-8 h-8 mx-auto text-gray-400" />
                    <span class="text-[10px] text-gray-400 font-medium block">No Photo</span>
                </div>
            @endif

        </div>

        <!-- UPLOAD BUTTON CONTAINER WITH EXPLICIT STYLES -->
        <div class="space-y-2">
            <label 
                for="custom-teacher-photo-input" 
                style="background-color: #d97706 !important; color: #ffffff !important; display: inline-flex !important; align-items: center !important; gap: 8px !important; padding: 10px 16px !important; border-radius: 8px !important; font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important; text-decoration: none !important;"
            >
                <x-heroicon-m-arrow-up-tray style="width: 16px !important; height: 16px !important; color: #ffffff !important;" />
                <span style="color: #ffffff !important;">Select & Upload Teacher Photo</span>
            </label>

            <!-- Hidden file input linked to Livewire property -->
            <input 
                type="file" 
                id="custom-teacher-photo-input" 
                wire:model.live="newPhoto" 
                accept="image/png,image/jpeg,image/jpg,image/webp"
                class="hidden"
            >

            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                @if($livewire->newPhoto)
                    <span class="text-success-600 dark:text-success-400 font-bold block truncate" style="max-width: 250px;">
                        ✓ Selected: {{ $livewire->newPhoto->getClientOriginalName() }}
                    </span>
                @else
                    <span>Formats: PNG, JPG, WEBP (Max 2MB)</span>
                @endif
            </div>
        </div>

    </div>
</div>