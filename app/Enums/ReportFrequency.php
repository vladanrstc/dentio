<?php

namespace App\Enums;

enum ReportFrequency: string
{
    case OFF = 'off';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
