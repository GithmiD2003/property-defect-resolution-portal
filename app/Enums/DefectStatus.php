<?php

namespace App\Enums;

enum DefectStatus: string
{
    case Reported = 'reported';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Repaired = 'repaired';
    case Verified = 'verified';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Repaired => 'Repaired',
            self::Verified => 'Verified',
            self::Reopened => 'Reopened',
        };
    }
}
