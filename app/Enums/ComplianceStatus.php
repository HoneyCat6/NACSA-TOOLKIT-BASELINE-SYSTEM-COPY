<?php

namespace App\Enums;

/**
 * Indicative Cyber Security Act 2024 compliance status (DECISIONS D-50). Not a legal determination.
 */
enum ComplianceStatus: string
{
    case Compliant = 'Compliant';
    case PartiallyCompliant = 'Partially compliant';
    case NonCompliant = 'Non-compliant';
}
