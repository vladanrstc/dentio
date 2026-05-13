<?php

namespace App\Enums;

enum PatientTaskStatus: string
{
    case OPEN = 'open';
    case DONE = 'done';
    case CANCELLED = 'cancelled';
}
