<?php

namespace App\Filament\Traits;

trait HasAdminOnlyAccess
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Only allow Super Admin, Admin, or users with explicit role permissions
        return in_array($user->type, ['super_admin', 'admin']) 
            || $user->hasRole(['super_admin', 'admin']);
    }
}