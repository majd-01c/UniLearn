<?php

namespace App\Enum;

enum JobApplicationStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case REVIEWED = 'REVIEWED';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
}
