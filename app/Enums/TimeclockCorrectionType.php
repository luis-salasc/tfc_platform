<?php

namespace App\Enums;

enum TimeclockCorrectionType: string
{
    case Add = 'add';
    case Correct = 'correct';
    case Annul = 'annul';

    public function label(): string
    {
        return match ($this) {
            self::Add => 'Añadir fichaje',
            self::Correct => 'Corregir fichaje',
            self::Annul => 'Anular fichaje',
        };
    }
}
