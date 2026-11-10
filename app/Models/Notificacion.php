<?php

namespace App\Models;

use App\Core\Model;

class Notificacion extends Model
{
    protected static string $tabla = 'notificaciones';

    public static function deUsuario(int $usuarioId, bool $soloNoLeidas, int $limite): array
    {
        $sql = 'SELECT id, tipo, titulo, mensaje, enlace, leida, creado_en FROM notificaciones WHERE usuario_id = ?'
            . ($soloNoLeidas ? ' AND leida = 0' : '') . ' ORDER BY creado_en DESC, id DESC LIMIT ' . $limite;
        return static::todos($sql, [$usuarioId]);
    }

    public static function noLeidas(int $usuarioId): int
    {
        return (int) static::valor('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0', [$usuarioId]);
    }

    /** Solo el dueño de la notificación puede marcarla o borrarla. */
    public static function marcarLeida(int $id, int $usuarioId): bool
    {
        $existe = static::valor('SELECT COUNT(*) FROM notificaciones WHERE id = ? AND usuario_id = ?', [$id, $usuarioId]);
        if (!$existe) {
            return false;
        }
        static::ejecutar('UPDATE notificaciones SET leida = 1 WHERE id = ?', [$id]);
        return true;
    }

    public static function marcarTodas(int $usuarioId): int
    {
        return static::ejecutar('UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0', [$usuarioId]);
    }

    public static function borrar(int $id, int $usuarioId): bool
    {
        return static::ejecutar('DELETE FROM notificaciones WHERE id = ? AND usuario_id = ?', [$id, $usuarioId]) > 0;
    }
}
