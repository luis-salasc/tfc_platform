<?php

namespace App\Enums;

enum TimeclockEventType: string
{
    case ClockIn = 'clock_in';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
    case ClockOut = 'clock_out';

    public function label(): string
    {
        return match ($this) {
            self::ClockIn => 'Fichar entrada',
            self::BreakStart => 'Iniciar pausa',
            self::BreakEnd => 'Finalizar pausa',
            self::ClockOut => 'Fichar salida',
        };
    }

    public function stateLabel(): string
    {
        return match ($this) {
            self::ClockIn, self::BreakEnd => 'Trabajando',
            self::BreakStart => 'En pausa',
            self::ClockOut => 'Jornada finalizada',
        };
    }
}
