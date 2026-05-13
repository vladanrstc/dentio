<?php

namespace App\Enums;

enum PatientStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case TRANSFERRED = 'transferred';
    case COMPLETED = 'completed';
}
