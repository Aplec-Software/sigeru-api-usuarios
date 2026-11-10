<?php

namespace App\Models;

use App\Core\Clock;
use App\Core\Model;

/** Sesiones abiertas: permiten invalidar un token antes de que venza. */
class Sesion extends Model
{
    protected static string $tabla = 'sesiones';
    protected static array $campos = ['usuario_id', 'jti', 'ip', 'agente', 'emitido_en', 'expira_en', 'revocado_en'];

    public static function revocar(string $jti): void
    {
        static::ejecutar('UPDATE sesiones SET revocado_en = ? WHERE jti = ? AND revocado_en IS NULL', [Clock::ahora(), $jti]);
    }

    public static function revocarTodas(int $usuarioId, ?string $excepto = null): int
    {
        return static::ejecutar(
            'UPDATE sesiones SET revocado_en = ? WHERE usuario_id = ? AND revocado_en IS NULL AND jti <> ?',
            [Clock::ahora(), $usuarioId, $excepto ?? '']
        );
    }
}
