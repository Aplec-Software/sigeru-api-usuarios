<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Jwt;
use App\Core\Lang;
use App\Core\Notifier;
use App\Core\Params;
use App\Core\Request;
use App\Core\Response;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Views\UsuarioView;

/**
 * Registro, inicio y cierre de sesión.
 */
class AuthController extends Controller
{
    // Hash de una clave cualquiera: se usa para que la respuesta tarde lo mismo
    // exista o no el correo (así no se puede averiguar qué correos están registrados).
    private const HASH_FICTICIO = '$2y$12$cNcoV1F.LUuW2qJ0Bn7uZ.6RCqwZ8f3f8z8Q9Jb2W7oVb2kJ7m6aK';

    public function login(Request $req): Response
    {
        $datos = $this->validar($req, [
            'email' => 'required|email|max:120',
            'password' => 'required|string|max:200',
        ]);
        $usuario = Usuario::buscarPorEmail($datos['email']);

        if ($usuario === null) {
            password_verify($datos['password'], self::HASH_FICTICIO);
            throw new HttpException(401, Lang::t('auth.credenciales'));
        }
        if ($usuario['bloqueado_hasta'] !== null && $usuario['bloqueado_hasta'] > Clock::ahora()) {
            throw new HttpException(429, Lang::t('auth.bloqueado', ['hasta' => substr($usuario['bloqueado_hasta'], 11, 5)]));
        }
        if (!password_verify($datos['password'], $usuario['password_hash'])) {
            Usuario::registrarIntentoFallido($usuario, Params::int('login_max_intentos', 5), Params::int('login_bloqueo_minutos', 15));
            Audit::registrar($req, 'login_fallido', 'usuario', $usuario['id']);
            throw new HttpException(401, Lang::t('auth.credenciales'));
        }
        if ($usuario['estado'] !== 'activo') {
            throw new HttpException(403, Lang::t('auth.cuenta_' . $usuario['estado']));
        }

        // Si cambió el costo de bcrypt, se vuelve a calcular el hash con el nuevo costo
        if (password_needs_rehash($usuario['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            Usuario::actualizar($usuario['id'], ['password_hash' => password_hash($datos['password'], PASSWORD_BCRYPT, ['cost' => 12])]);
        }
        Usuario::registrarAccesoCorrecto($usuario['id']);

        $minutos = Params::int('jwt_duracion_minutos', 480);
        $jti = bin2hex(random_bytes(16));
        $ahora = time();
        $token = Jwt::crear([
            'iss' => Auth::EMISOR,
            'sub' => (int) $usuario['id'],
            'rol' => $usuario['rol'],
            'nombre' => $usuario['nombre'] . ' ' . $usuario['apellido'],
            'idioma' => $usuario['idioma'],
            'jti' => $jti,
            'iat' => $ahora,
            'nbf' => $ahora,
            'exp' => $ahora + $minutos * 60,
        ], $this->clavePrivada());

        Sesion::crear([
            'usuario_id' => $usuario['id'],
            'jti' => $jti,
            'ip' => $req->ip,
            'agente' => mb_substr($req->header('User-Agent') ?? '', 0, 255),
            'emitido_en' => Clock::ahora(),
            'expira_en' => Clock::mas($minutos),
        ]);
        $req->usuario = ['id' => (int) $usuario['id']];
        Audit::registrar($req, 'login', 'usuario', $usuario['id']);

        return Response::ok([
            'token' => $token,
            'expira_en' => Clock::mas($minutos),
            'usuario' => UsuarioView::render(Usuario::buscar((int) $usuario['id'])),
        ], 200, null, Lang::t('auth.bienvenido', ['nombre' => $usuario['nombre']]))
            ->conHeader('Set-Cookie', $this->cookie($token, $minutos * 60));
    }

    public function logout(Request $req): Response
    {
        Sesion::revocar($req->usuario['jti']);
        Audit::registrar($req, 'logout', 'usuario', $req->usuario['id']);
        return Response::ok(null, 200, null, Lang::t('auth.sesion_cerrada'))
            ->conHeader('Set-Cookie', $this->cookie('', 0));
    }

    public function me(Request $req): Response
    {
        $usuario = Usuario::buscar($req->usuario['id']) ?? throw $this->noEncontrado();
        return Response::ok(UsuarioView::render($usuario));
    }

    /**
     * Solicitud de registro desde la landing o el frontend.
     * Los vecinos pueden quedar activos al instante (según parámetro); el
     * personal municipal queda pendiente hasta que un administrador lo apruebe.
     */
    public function registro(Request $req): Response
    {
        // Campo trampa: es invisible para las personas, solo lo completan los robots
        if (!empty($req->input('sitio_web'))) {
            return Response::ok(null, 201, null, Lang::t('usuarios.registro_pendiente'));
        }
        $req->body['rol'] = $req->input('rol') ?: 'vecino';
        $reglas = [
            'nombre' => 'required|string|min:2|max:60',
            'apellido' => 'required|string|min:2|max:60',
            'email' => 'required|email|max:120',
            'telefono' => 'nullable|telefono',
            'password' => 'required|password',
            'rol' => 'required|in:vecino,cuadrilla,operario',
            'idioma' => 'nullable|in:es,en',
        ];
        if ($req->body['rol'] !== 'vecino') {
            $reglas['documento'] = 'required|ci';   // el personal municipal se identifica con su cédula
        }
        $datos = $this->validar($req, $reglas);
        $datos['email'] = mb_strtolower($datos['email']);

        if (Usuario::existe('email', $datos['email'])) {
            throw $this->conflicto('usuarios.email_existente');
        }
        if (!empty($datos['documento']) && Usuario::existe('documento', $datos['documento'])) {
            throw $this->conflicto('usuarios.documento_existente');
        }

        $activo = $datos['rol'] === 'vecino' && Params::get('registro_vecino_autoaprobado', '1') === '1';
        $id = Usuario::crear([
            'rol_id' => Usuario::idDeRol($datos['rol']),
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'documento' => $datos['documento'] ?? null,
            'email' => $datos['email'],
            'telefono' => $datos['telefono'] ?? null,
            'password_hash' => password_hash($datos['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'estado' => $activo ? 'activo' : 'pendiente',
            'idioma' => $datos['idioma'] ?? 'es',
            'creado_en' => Clock::ahora(),
        ]);
        Audit::registrar($req, 'registro', 'usuario', $id, $datos['rol']);

        if (!$activo) {
            Notifier::aRol('administrador', 'solicitud_registro', Lang::t('usuarios.nueva_solicitud'),
                "{$datos['nombre']} {$datos['apellido']} ({$datos['rol']}) solicitó acceso al sistema", '#/usuarios?estado=pendiente');
        }
        return Response::ok(['id' => $id, 'estado' => $activo ? 'activo' : 'pendiente'], 201, null,
            Lang::t($activo ? 'usuarios.registro_activo' : 'usuarios.registro_pendiente'));
    }

    private function clavePrivada(): string
    {
        $pem = Config::get('JWT_CLAVE_PRIVADA_PEM');
        if ($pem !== null) {
            return $pem;
        }
        $ruta = Config::get('JWT_CLAVE_PRIVADA', '/claves/jwt_privada.pem');
        if (!is_readable($ruta)) {
            throw new \RuntimeException('No se encuentra la clave privada del JWT');
        }
        return file_get_contents($ruta);
    }

    /** Cookie HttpOnly (JavaScript no la puede leer) y SameSite=Strict (no viaja desde otros sitios). */
    private function cookie(string $valor, int $segundos): string
    {
        $segura = Config::get('COOKIE_SEGURA', '1') === '1' ? '; Secure' : '';
        return Auth::COOKIE . '=' . $valor . '; Path=/; Max-Age=' . $segundos . '; HttpOnly; SameSite=Strict' . $segura;
    }
}
