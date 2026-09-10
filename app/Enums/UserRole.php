<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Contractor = 'contractor';
    case Owner = 'owner';
}
