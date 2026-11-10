<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Clock;
use App\Core\CrudController;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Notifier;
use App\Core\Request;
use App\Core\Response;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Views\UsuarioView;

/**
 * ABM de usuarios de todos los roles (solo administradores).
 */
class UsuarioController extends CrudController
{
    protected string $modelo = Usuario::class;
    protected string $entidad = 'usuario';
    protected array $reglas = [
        'rol_id' => 'required|int|in:1,2,3,4',
        'nombre' => 'required|string|min:2|max:60',
        'apellido' => 'required|string|min:2|max:60',
        'documento' => 'nullable|ci',
        'email' => 'required|email|max:120',
        'telefono' => 'nullable|telefono',
        'password' => 'required|password',
        'estado' => 'nullable|in:pendiente,activo,inactivo,rechazado',
        'idioma' => 'nullable|in:es,en',
        'centro_id' => 'nullable|int',
    ];

    public function vista(array $fila): array
    {
        return UsuarioView::render($fila);
    }

    protected function filtrosExtra(Request $req): array
    {
        // permite filtrar también por nombre de rol: ?rol=cuadrilla
        return !empty($req->query['rol']) ? ['r.nombre = ?' => $req->query['rol']] : [];
    }

    protected function antesDeGuardar(Request $req, array &$datos, ?array $actual): void
    {
        if (isset($datos['email'])) {
            $datos['email'] = mb_strtolower($datos['email']);
            if (Usuario::existe('email', $datos['email'], $actual['id'] ?? null)) {
                throw $this->conflicto('usuarios.email_existente');
            }
        }
        if (!empty($datos['documento']) && Usuario::existe('documento', $datos['documento'], $actual['id'] ?? null)) {
            throw $this->conflicto('usuarios.documento_existente');
        }
        if (isset($datos['password'])) {
            $datos['password_hash'] = password_hash($datos['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            unset($datos['password']);
        }

        // RNE: el personal municipal debe tener cédula y el operario, un centro asignado
        $rol = (int) ($datos['rol_id'] ?? $actual['rol_id'] ?? 4);
        $documento = array_key_exists('documento', $datos) ? $datos['documento'] : ($actual['documento'] ?? null);
        if ($rol !== 4 && empty($documento)) {
            throw new HttpException(422, Lang::t('error.validacion'), ['documento' => Lang::t('usuarios.documento_requerido')]);
        }
        $centro = array_key_exists('centro_id', $datos) ? $datos['centro_id'] : ($actual['centro_id'] ?? null);
        if ($rol === 3 && empty($centro)) {
            throw new HttpException(422, Lang::t('error.validacion'), ['centro_id' => Lang::t('usuarios.centro_requerido')]);
        }
        if ($rol !== 3) {
            $datos['centro_id'] = null;
        }

        if ($actual === null) {
            $datos['estado'] = $datos['estado'] ?? 'activo';
            $datos['creado_en'] = Clock::ahora();
        } elseif ((int) $actual['id'] === $req->usuario['id']) {
            // un administrador no puede quitarse a sí mismo el rol ni desactivarse
            if ((isset($datos['rol_id']) && $datos['rol_id'] !== 1) || (isset($datos['estado']) && $datos['estado'] !== 'activo')) {
                throw $this->conflicto('usuarios.no_auto_modificar');
            }
        }
    }

    protected function despuesDeGuardar(Request $req, int $id, array $datos, ?array $actual): void
    {
        if ($actual !== null && isset($datos['estado']) && $datos['estado'] !== 'activo') {
            Sesion::revocarTodas($id);
        }
    }

    /** Baja lógica: el usuario queda inactivo (se conservan sus registros históricos). */
    public function destroy(Request $req): Response
    {
        $id = $this->idParam($req);
        Usuario::buscar($id) ?? throw $this->noEncontrado();
        if ($id === $req->usuario['id']) {
            throw $this->conflicto('usuarios.no_auto_modificar');
        }
        Usuario::actualizar($id, ['estado' => 'inactivo']);
        Sesion::revocarTodas($id);
        Audit::registrar($req, 'baja', 'usuario', $id);
        return Response::ok(null, 200, null, Lang::t('usuarios.dado_de_baja'));
    }

    /** Aprobar, rechazar, activar o desactivar una cuenta. */
    public function estado(Request $req): Response
    {
        $id = $this->idParam($req);
        $usuario = Usuario::buscar($id) ?? throw $this->noEncontrado();
        $datos = $this->validar($req, [
            'estado' => 'required|in:activo,inactivo,rechazado',
            'motivo' => 'nullable|string|max:200',
        ]);
        if ($id === $req->usuario['id']) {
            throw $this->conflicto('usuarios.no_auto_modificar');
        }
        Usuario::actualizar($id, ['estado' => $datos['estado']]);
        if ($datos['estado'] !== 'activo') {
            Sesion::revocarTodas($id);
        }
        if ($usuario['estado'] === 'pendiente' && $datos['estado'] === 'activo') {
            Notifier::aUsuarios([$id], 'cuenta_aprobada', Lang::t('usuarios.cuenta_aprobada'), Lang::t('usuarios.cuenta_aprobada_detalle'));
        }
        Audit::registrar($req, 'estado_' . $datos['estado'], 'usuario', $id, $datos['motivo'] ?? null);
        return Response::ok(UsuarioView::render(Usuario::buscar($id)), 200, null, Lang::t('ok.modificado'));
    }
}
