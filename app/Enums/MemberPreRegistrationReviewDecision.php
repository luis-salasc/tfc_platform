<?php

namespace App\Enums;

enum MemberPreRegistrationReviewDecision: string
{
    case CanContinue = 'can_continue';
    case DoNotContinueForNow = 'do_not_continue_for_now';

    public function label(): string
    {
        return match ($this) {
            self::CanContinue => 'Puede continuar',
            self::DoNotContinueForNow => 'No continuar por ahora',
        };
    }
}
