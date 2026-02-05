<?php

namespace App\Enum;

enum QuestionType: string
{
    case MCQ = 'MCQ';
    case TRUE_FALSE = 'TRUE_FALSE';
    case TEXT = 'TEXT';
}
