<x-filament-panels::page>
    
    {{-- TOP SECTION: Generation Form --}}
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

    {{-- BOTTOM SECTION: Table of Generated Classes --}}
    <div class="mt-8">
        <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white mb-6">
            Recent Class Generations
        </h2>
        
        {{-- --- ADDED: THE TABS MENU --- --}}
        @if (count($tabs = $this->getTabs()))
            <div class="mb-4 flex w-full overflow-x-auto">
                <x-filament::tabs>
                    @foreach ($tabs as $tabKey => $tab)
                        <x-filament::tabs.item
                            :active="$activeTab === $tabKey || ($activeTab === null && $tabKey === 'all')"
                            wire:click="$set('activeTab', '{{ $tabKey }}')"
                        >
                            {{ $tab->getLabel() }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>
        @endif

        {{ $this->table }}
    </div>

</x-filament-panels::page>