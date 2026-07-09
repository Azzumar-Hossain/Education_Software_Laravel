<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Users'),
            
            'students' => Tab::make('Students')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'student'))
                ->badgeColor('success'),
                
            'teachers' => Tab::make('Teachers')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'teacher'))
                ->badgeColor('info'),
                
            'admins' => Tab::make('Admins')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', ['admin', 'super_admin']))
                ->badgeColor('warning'),
        ];
    }
}