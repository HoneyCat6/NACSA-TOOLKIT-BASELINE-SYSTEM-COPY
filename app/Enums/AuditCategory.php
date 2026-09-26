<?php

namespace App\Enums;

enum AuditCategory: string
{
    case Technical = 'technical';
    case Compliance = 'compliance';
}
