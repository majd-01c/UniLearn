<?php

namespace App\Enum;

enum ContenuType: string
{
    case VIDEO = 'VIDEO';
    case PDF = 'PDF';
    case QUIZ = 'QUIZ';
    case TEXT = 'TEXT';
    case EXERCICE = 'EXERCICE';
    case COURS = 'COURS';
}
