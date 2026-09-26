<?php

namespace App\Enums;

enum OtpResult
{
    case Verified;
    case Invalid;
    case Expired;
    case Locked;
}
