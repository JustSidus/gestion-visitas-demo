<?php

namespace App\Enums;

enum EnumDocumentType: int
{
    case CEDULA = 1;
    case PASAPORTE = 2;
    case SIN_IDENTIFICACION = 3;

    public static function getValues(): array
    {
        return [
            self::CEDULA->value,
            self::PASAPORTE->value,
            self::SIN_IDENTIFICACION->value
        ];
    }
}
