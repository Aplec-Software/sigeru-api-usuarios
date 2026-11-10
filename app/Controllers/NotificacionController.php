<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notificacion;
use App\Views\JsonView;

/**
 * Notificaciones del usuario autenticado (alertas del sistema).
 */
class NotificacionController extends Controller
{
    public function index(Request $req): Response
    {
        $usuario = $req->usuario['id'];
        $soloNoLeidas = ($req->query['no_leidas'] ?? '0') === '1';
        $filas = Notificacion::deUsuario($usuario, $soloNoLeidas, $req->queryInt('limite', 20, 100));
        return Response::ok(array_map(fn ($n) => JsonView::render($n), $filas), 200, ['no_leidas' => Notificacion::noLeidas($usuario)]);
    }

    public function marcar(Request $req): Response
    {
        if (!Notificacion::marcarLeida($this->idParam($req), $req->usuario['id'])) {
            throw $this->noEncontrado();
        }
        return Response::ok(null, 200, ['no_leidas' => Notificacion::noLeidas($req->usuario['id'])], Lang::t('ok.modificado'));
    }

    public function marcarTodas(Request $req): Response
    {
        $cantidad = Notificacion::marcarTodas($req->usuario['id']);
        return Response::ok(['marcadas' => $cantidad], 200, ['no_leidas' => 0], Lang::t('ok.modificado'));
    }

    public function destroy(Request $req): Response
    {
        if (!Notificacion::borrar($this->idParam($req), $req->usuario['id'])) {
            throw $this->noEncontrado();
        }
        return Response::ok(null, 200, null, Lang::t('ok.eliminado'));
    }
}
