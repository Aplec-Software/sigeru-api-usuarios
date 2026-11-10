<?php

namespace App\Views;

/** Representación pública de un usuario: nunca incluye el hash de la contraseña. */
class UsuarioView
{
    public static function render(array $u): array
    {
        return JsonView::render($u, [], ['password_hash', 'intentos_fallidos', 'bloqueado_hasta']);
    }
}
