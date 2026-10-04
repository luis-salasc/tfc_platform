<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Registered = 'registered';
    case Active = 'active';
    case Inactive = 'inactive';
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registrado',
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Frozen => 'Congelado',
        };
    }
}
