<?php

namespace App\Enums;

enum MemberPreRegistrationStatus: string
{
    case Draft = 'draft';
    case RequiresReview = 'requires_review';
    case OnHold = 'on_hold';
    case ReadyForFinalization = 'ready_for_finalization';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::RequiresReview => 'Requiere revisión',
            self::OnHold => 'En espera',
            self::ReadyForFinalization => 'Lista para formalización',
        };
    }
}
