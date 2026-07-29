<x-filament-panels::page>
    <x-filament-panels::form wire:submit="downloadMarksheets">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button
                type="submit"
                icon="heroicon-o-arrow-down-tray"
                color="primary"
                size="lg">
                Download Class & Section Marksheets (PDF)
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
