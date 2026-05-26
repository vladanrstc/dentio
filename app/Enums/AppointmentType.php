<?php

namespace App\Enums;

enum AppointmentType: string
{
    case CHECKUP = 'checkup';
    case INTERVENTION = 'intervention';
    case CONTROL = 'control';
}
