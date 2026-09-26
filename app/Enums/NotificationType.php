<?php

namespace App\Enums;

enum NotificationType: string
{
    case Reminder = 'reminder';
    case Escalation = 'escalation';
}
