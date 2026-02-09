<?php

namespace App\Enum;

enum TopicStatus: string
{
    case OPEN = 'open';
    case SOLVED = 'solved';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::SOLVED => 'Solved',
            self::LOCKED => 'Locked',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OPEN => 'primary',
            self::SOLVED => 'success',
            self::LOCKED => 'secondary',
        };
    }
}
