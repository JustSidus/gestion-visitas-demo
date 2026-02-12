<?php

namespace App\Enums;

enum EnumVisitStatuses: int
{
    case ABIERTO = 1;
    case CERRADO = 2;

    public static function getValues(): array
    {
        return [
            self::ABIERTO->value,
            self::CERRADO->value
        ];
    }
}
