<?php

namespace App\Models;

use App\Core\Clock;
use App\Core\Model;

class Usuario extends Model
{
    protected static string $tabla = 'usuarios';
    protected static array $campos = [
        'rol_id', 'nombre', 'apellido', 'documento', 'email', 'telefono', 'password_hash',
        'estado', 'idioma', 'centro_id', 'intentos_fallidos', 'bloqueado_hasta', 'ultimo_acceso', 'creado_en',
    ];
    protected static array $filtros = ['rol_id', 'estado', 'centro_id'];
    protected static array $busqueda = ['t.nombre', 't.apellido', 't.email', 't.documento'];
    protected static string $orden = 't.apellido, t.nombre';

    protected static function consultaBase(): string
    {
        return 'SELECT t.*, r.nombre AS rol, ci.cuadrilla_id, cu.nombre AS cuadrilla, ce.nombre AS centro
                FROM usuarios t
                JOIN roles r ON r.id = t.rol_id
                LEFT JOIN cuadrilla_integrante ci ON ci.usuario_id = t.id
                LEFT JOIN cuadrillas cu ON cu.id = ci.cuadrilla_id
                LEFT JOIN centros ce ON ce.id = t.centro_id';
    }

    public static function buscarPorEmail(string $email): ?array
    {
        return static::uno(static::consultaBase() . ' WHERE t.email = ?', [mb_strtolower($email)]);
    }

    public static function idDeRol(string $nombre): ?int
    {
        $id = static::valor('SELECT id FROM roles WHERE nombre = ?', [$nombre]);
        return $id === false ? null : (int) $id;
    }

    /** Suma un intento fallido y, si llega al máximo, bloquea la cuenta unos minutos. */
    public static function registrarIntentoFallido(array $usuario, int $maximo, int $minutosBloqueo): void
    {
        $intentos = (int) $usuario['intentos_fallidos'] + 1;
        $bloqueo = $intentos >= $maximo ? Clock::mas($minutosBloqueo) : null;
        static::ejecutar('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?', [
            $bloqueo ? 0 : $intentos, $bloqueo, $usuario['id'],
        ]);
    }

    public static function registrarAccesoCorrecto(int $id): void
    {
        static::ejecutar('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = ? WHERE id = ?', [Clock::ahora(), $id]);
    }

    public static function idsAdministradores(): array
    {
        return array_column(static::todos("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'administrador' AND u.estado = 'activo'"), 'id');
    }
}
