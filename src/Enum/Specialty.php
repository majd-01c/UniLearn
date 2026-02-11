<?php

namespace App\Enum;

enum Specialty: string
{
    case COMPUTER_SCIENCE = 'computer_science';
    case MATHEMATICS = 'mathematics';
    case PHYSICS = 'physics';
    case CHEMISTRY = 'chemistry';
    case BIOLOGY = 'biology';
    case ENGINEERING = 'engineering';
    case BUSINESS = 'business';
    case ECONOMICS = 'economics';
    case LAW = 'law';
    case MEDICINE = 'medicine';
    case LANGUAGES = 'languages';
    case ARTS = 'arts';
    case OTHER = 'other';
    
    public function label(): string
    {
        return match($this) {
            self::COMPUTER_SCIENCE => 'Computer Science',
            self::MATHEMATICS => 'Mathematics',
            self::PHYSICS => 'Physics',
            self::CHEMISTRY => 'Chemistry',
            self::BIOLOGY => 'Biology',
            self::ENGINEERING => 'Engineering',
            self::BUSINESS => 'Business Administration',
            self::ECONOMICS => 'Economics',
            self::LAW => 'Law',
            self::MEDICINE => 'Medicine',
            self::LANGUAGES => 'Languages & Literature',
            self::ARTS => 'Arts & Design',
            self::OTHER => 'Other',
        };
    }
}
