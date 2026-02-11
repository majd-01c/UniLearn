<?php

namespace App\Enum;

enum Level: string
{
    case L1 = 'L1';
    case L2 = 'L2';
    case L3 = 'L3';
    case M1 = 'M1';
    case M2 = 'M2';
    
    public function label(): string
    {
        return match($this) {
            self::L1 => '1st Year (L1)',
            self::L2 => '2nd Year (L2)',
            self::L3 => '3rd Year (L3)',
            self::M1 => 'Master 1 (M1)',
            self::M2 => 'Master 2 (M2)',
        };
    }
}
