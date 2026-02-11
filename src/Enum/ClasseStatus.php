<?php

namespace App\Enum;

enum ClasseStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case FULL = 'full';
    
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::FULL => 'Full',
        };
    }
    
    public function badgeClass(): string
    {
        return match($this) {
            self::ACTIVE => 'bg-success',
            self::INACTIVE => 'bg-secondary',
            self::FULL => 'bg-danger',
        };
    }
}
