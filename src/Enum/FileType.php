<?php

namespace App\Enum;

enum FileType: string
{
    case PDF = 'PDF';
    case VIDEO = 'VIDEO';
    case IMAGE = 'IMAGE';
    case AUDIO = 'AUDIO';
    case WORD = 'WORD';
    case EXCEL = 'EXCEL';
    case PPT = 'PPT';
}
