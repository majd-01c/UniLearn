<?php

namespace App\Enum;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case STUDENT = 'STUDENT';
    case TEACHER = 'TEACHER';
    case BUSINESS_PARTNER = 'BUSINESS_PARTNER';
}
