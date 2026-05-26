<?php

namespace App\Enums;

enum UserRole: string
{
    case PLATFORM_ADMIN = 'platform_admin';
    case COMPANY_ADMIN = 'company_admin';
    case DENTIST = 'dentist';
    case NURSE = 'nurse';
}
