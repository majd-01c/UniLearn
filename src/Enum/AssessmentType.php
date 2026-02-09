<?php

namespace App\Enum;

enum AssessmentType: string
{
    case QUIZ = 'QUIZ';
    case EXAM = 'EXAM';
    case EXERCISE = 'EXERCISE';
}
