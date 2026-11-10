<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Parametro;
use App\Views\JsonView;

/**
 * Parámetros del sistema. Los públicos (idioma, mapa, nombre) los leen las
 * interfaces sin iniciar sesión; el resto solo el administrador.
 */
class ParametroController extends Controller
{
    public function publicos(Request $req): Response
    {
        $filas = Parametro::listarTodos(true);
        return Response::ok(array_column($filas, 'valor', 'clave'));
    }

    public function index(Request $req): Response
    {
        return Response::ok(array_map(fn ($p) => JsonView::render($p), Parametro::listarTodos(false)));
    }

    public function update(Request $req): Response
    {
        $clave = (string) $req->param('clave');
        $param = Parametro::porClave($clave) ?? throw $this->noEncontrado();
        $regla = match ($param['tipo']) {
            'entero' => 'required|int|min:0|max:100000',
            'decimal' => 'required|numeric',
            'booleano' => 'required|in:0,1',
            default => 'required|string|max:255',
        };
        $datos = Validator::validar($req->body, ['valor' => $regla]);
        Parametro::cambiar($clave, (string) $datos['valor']);
        Audit::registrar($req, 'modificar', 'parametro', $clave, (string) $datos['valor']);
        return Response::ok(JsonView::render(Parametro::porClave($clave)), 200, null, Lang::t('ok.modificado'));
    }
}
