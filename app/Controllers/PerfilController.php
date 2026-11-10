<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Views\UsuarioView;

/**
 * Cada usuario puede ver y modificar sus propios datos.
 */
class PerfilController extends Controller
{
    public function update(Request $req): Response
    {
        $datos = $this->validar($req, [
            'nombre' => 'required|string|min:2|max:60',
            'apellido' => 'required|string|min:2|max:60',
            'telefono' => 'nullable|telefono',
            'idioma' => 'required|in:es,en',
        ], true);
        Usuario::actualizar($req->usuario['id'], $datos);
        Audit::registrar($req, 'modificar_perfil', 'usuario', $req->usuario['id']);
        return Response::ok(UsuarioView::render(Usuario::buscar($req->usuario['id'])), 200, null, Lang::t('ok.modificado'));
    }

    public function cambiarClave(Request $req): Response
    {
        $datos = $this->validar($req, [
            'actual' => 'required|string|max:200',
            'nueva' => 'required|password',
        ]);
        $usuario = Usuario::buscar($req->usuario['id']);
        if (!password_verify($datos['actual'], $usuario['password_hash'])) {
            throw new HttpException(422, Lang::t('error.validacion'), ['actual' => Lang::t('usuarios.clave_actual_incorrecta')]);
        }
        if (password_verify($datos['nueva'], $usuario['password_hash'])) {
            throw new HttpException(422, Lang::t('error.validacion'), ['nueva' => Lang::t('usuarios.clave_repetida')]);
        }
        Usuario::actualizar($usuario['id'], ['password_hash' => password_hash($datos['nueva'], PASSWORD_BCRYPT, ['cost' => 12])]);
        // se cierran las demás sesiones abiertas por seguridad
        Sesion::revocarTodas($usuario['id'], $req->usuario['jti']);
        Audit::registrar($req, 'cambio_clave', 'usuario', $usuario['id']);
        return Response::ok(null, 200, null, Lang::t('usuarios.clave_cambiada'));
    }
}
