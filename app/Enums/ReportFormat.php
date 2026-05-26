<?php

namespace App\Enums;

enum ReportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case PDF = 'pdf';
}
