<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\Property;
use App\Models\User;

class DefectPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Manager,
            UserRole::Owner,
            UserRole::Contractor,
        ], true);
    }

    public function view(User $user, Defect $defect): bool
    {
        return match ($user->role) {
            UserRole::Manager => true,

            UserRole::Contractor => $defect->assigned_to === $user->id,

            UserRole::Owner => Property::query()
                ->whereKey($defect->property_id)
                ->whereHas('members', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->exists(),
        };
    }

    public function create(User $user, Property $property): bool
    {
        if ($user->role === UserRole::Manager) {
            return true;
        }

        return $user->role === UserRole::Owner
            && $property->members()
                ->where('users.id', $user->id)
                ->exists();
    }

    public function delete(User $user, Defect $defect): bool
    {
        return false;
    }
}
