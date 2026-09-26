<?php

namespace App\Enums;

enum ElementStatus: string
{
    case Scored = 'scored';
    case Unanswered = 'unanswered';
}
