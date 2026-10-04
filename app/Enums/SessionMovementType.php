<?php

namespace App\Enums;

enum SessionMovementType: string
{
    case Payment = 'payment';
    case Attendance = 'attendance';
    case Adjustment = 'adjustment';
    case Complimentary = 'complimentary';
    case LegacyImport = 'legacy_import';
}
