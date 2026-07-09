<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $settings = \App\Models\SiteSetting::first();
        @endphp
        
        <div class="flex items-center gap-4">
            @if($settings && $settings->logo)
                <img src="{{ asset('storage/' . $settings->logo) }}" alt="School Logo" class="h-20 rounded-lg shadow-sm">
            @endif
            
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ $settings->school_name_bn ?? ($settings->school_name_en ?? 'Welcome to Dashboard') }}
                </h2>
                <p class="text-gray-500 text-sm mt-1">
                    {{ $settings->address_bn ?? ($settings->address_en ?? 'Configure your school details in Site Settings.') }}
                </p>
                
                @if($settings && ($settings->phone || $settings->email))
                    <div class="flex gap-4 mt-2 text-xs text-gray-400">
                        @if($settings->phone) <span>📞 {{ $settings->phone }}</span> @endif
                        @if($settings->email) <span>✉️ {{ $settings->email }}</span> @endif
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>