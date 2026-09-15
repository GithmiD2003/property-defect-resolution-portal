<?php

namespace App\Policies;

use App\Enums\DefectStatus;
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

    public function assign(User $user, Defect $defect): bool
    {
        return $user->role === UserRole::Manager
            && in_array($defect->status, [
                DefectStatus::Reported,
                DefectStatus::Assigned,
                DefectStatus::Reopened,
            ], true);
    }

    public function startWork(User $user, Defect $defect): bool
    {
        return $user->role === UserRole::Contractor
            && $defect->assigned_to === $user->id
            && in_array($defect->status, [
                DefectStatus::Assigned,
                DefectStatus::Reopened,
            ], true);
    }

    public function markRepaired(User $user, Defect $defect): bool
    {
        return $user->role === UserRole::Contractor
            && $defect->assigned_to === $user->id
            && $defect->status === DefectStatus::InProgress;
    }

    public function verifyRepair(User $user, Defect $defect): bool
    {
        return $defect->status === DefectStatus::Repaired
            && $this->canReview($user, $defect);
    }

    public function reopen(User $user, Defect $defect): bool
    {
        return in_array($defect->status, [
            DefectStatus::Repaired,
            DefectStatus::Verified,
        ], true) && $this->canReview($user, $defect);
    }

    private function canReview(User $user, Defect $defect): bool
    {
        return match ($user->role) {
            UserRole::Manager => true,
            UserRole::Owner => $this->view($user, $defect),
            default => false,
        };
    }

    public function delete(User $user, Defect $defect): bool
    {
        return false;
    }
}
