<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Manager,
            UserRole::Owner,
        ], true);
    }

    public function view(User $user, Property $property): bool
    {
        if ($user->role === UserRole::Manager) {
            return true;
        }

        return $user->role === UserRole::Owner
            && $property->members()
                ->where('users.id', $user->getKey())
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function update(User $user, Property $property): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function manageMembers(User $user, Property $property): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function manageRooms(User $user, Property $property): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function handoverReport(User $user, Property $property): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function delete(User $user, Property $property): bool
    {
        return false;
    }
}
