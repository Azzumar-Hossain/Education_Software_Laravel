<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): User
    {
        // Check if user already exists (e.g., created from Teacher module)
        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            // Update name and type if changed
            $existingUser->update([
                'name' => $data['name'] ?? $existingUser->name,
                'type' => $data['type'] ?? $existingUser->type,
            ]);

            // Sync assigned roles
            if (isset($data['roles'])) {
                $existingUser->roles()->sync($data['roles']);
            }

            return $existingUser;
        }

        return parent::handleRecordCreation($data);
    }
}