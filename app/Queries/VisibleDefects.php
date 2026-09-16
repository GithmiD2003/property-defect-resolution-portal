<?php

namespace App\Queries;

use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VisibleDefects
{
    /** @return Builder<Defect> */
    public static function forUser(User $user): Builder
    {
        $query = Defect::query();

        return match ($user->role) {
            UserRole::Manager => $query,

            UserRole::Owner => $query->whereHas(
                'property.members',
                function ($members) use ($user) {
                    $members->where('users.id', $user->id);
                },
            ),

            UserRole::Contractor => $query
                ->where('assigned_to', $user->id),
        };
    }
}
