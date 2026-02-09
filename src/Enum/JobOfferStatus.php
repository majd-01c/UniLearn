<?php

namespace App\Enum;

enum JobOfferStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case REJECTED = 'REJECTED';
    case CLOSED = 'CLOSED';
}
