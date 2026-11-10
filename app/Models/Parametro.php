<?php

namespace App\Models;

use App\Core\Model;

class Parametro extends Model
{
    protected static string $tabla = 'parametros';

    public static function listarTodos(bool $soloPublicos): array
    {
        return static::todos('SELECT clave, valor, tipo, descripcion, publico FROM parametros'
            . ($soloPublicos ? ' WHERE publico = 1' : '') . ' ORDER BY clave');
    }

    public static function porClave(string $clave): ?array
    {
        return static::uno('SELECT clave, valor, tipo, descripcion, publico FROM parametros WHERE clave = ?', [$clave]);
    }

    public static function cambiar(string $clave, string $valor): void
    {
        static::ejecutar('UPDATE parametros SET valor = ? WHERE clave = ?', [$valor, $clave]);
    }
}
