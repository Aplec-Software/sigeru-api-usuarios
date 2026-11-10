<?php

namespace Tests\Feature;

use Tests\Support\TestCase;

class AuthTest extends TestCase
{
    public function testLoginCorrectoDevuelveTokenYCookieSegura(): void
    {
        $res = $this->pedir('POST', '/auth/login', ['email' => 'admin@sigeru.uy', 'password' => 'Sigeru2026!']);
        $this->assertSame(200, $res->status);
        $datos = $res->datos()['datos'];
        $this->assertSame('administrador', $datos['usuario']['rol']);
        $this->assertArrayNotHasKey('password_hash', $datos['usuario']);
        $this->assertCount(3, explode('.', $datos['token']));
        $this->assertStringContainsString('HttpOnly', $res->headers['Set-Cookie']);
        $this->assertStringContainsString('SameSite=Strict', $res->headers['Set-Cookie']);
        $this->assertSame(1, $this->contar('sesiones'));
    }

    public function testCredencialesIncorrectasDevuelve401(): void
    {
        $res = $this->pedir('POST', '/auth/login', ['email' => 'admin@sigeru.uy', 'password' => 'Incorrecta1!']);
        $this->assertSame(401, $res->status);
        $res = $this->pedir('POST', '/auth/login', ['email' => 'nadie@sigeru.uy', 'password' => 'Incorrecta1!']);
        $this->assertSame(401, $res->status);
    }

    public function testBloqueaLaCuentaLuegoDeCincoIntentos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->pedir('POST', '/auth/login', ['email' => 'ana.martinez@correo.uy', 'password' => 'Incorrecta1!']);
        }
        $res = $this->pedir('POST', '/auth/login', ['email' => 'ana.martinez@correo.uy', 'password' => 'Sigeru2026!']);
        $this->assertSame(429, $res->status);
    }

    public function testCuentaPendienteNoPuedeIngresar(): void
    {
        $res = $this->pedir('POST', '/auth/login', ['email' => 'mcabrera@sigeru.uy', 'password' => 'Sigeru2026!']);
        $this->assertSame(403, $res->status);
    }

    public function testDatosInvalidosDevuelve422(): void
    {
        $res = $this->pedir('POST', '/auth/login', ['email' => 'no-es-un-correo']);
        $this->assertSame(422, $res->status);
        $this->assertArrayHasKey('password', $res->datos()['errores']);
    }

    public function testRegistroDeVecinoQuedaActivo(): void
    {
        $res = $this->pedir('POST', '/auth/registro', [
            'nombre' => 'Rosa', 'apellido' => 'Pérez', 'email' => 'rosa@correo.uy', 'password' => 'ClaveSegura#1',
        ]);
        $this->assertSame(201, $res->status);
        $this->assertSame('activo', $res->datos()['datos']['estado']);
    }

    public function testRegistroDePersonalQuedaPendienteYAvisaAlAdministrador(): void
    {
        $antes = $this->contar('notificaciones', 'usuario_id = 1');
        $res = $this->pedir('POST', '/auth/registro', [
            'nombre' => 'Hugo', 'apellido' => 'Vera', 'email' => 'hvera@sigeru.uy', 'password' => 'ClaveSegura#1',
            'rol' => 'cuadrilla', 'documento' => '1.911.936-5',
        ]);
        $this->assertSame(201, $res->status);
        $this->assertSame('pendiente', $res->datos()['datos']['estado']);
        $this->assertSame($antes + 1, $this->contar('notificaciones', 'usuario_id = 1'));
    }

    public function testRegistroConCorreoRepetidoDevuelve409(): void
    {
        $res = $this->pedir('POST', '/auth/registro', [
            'nombre' => 'Otra', 'apellido' => 'Ana', 'email' => 'ANA.MARTINEZ@correo.uy', 'password' => 'ClaveSegura#1',
        ]);
        $this->assertSame(409, $res->status);
    }

    public function testRegistroDePersonalSinCedulaValidaDevuelve422(): void
    {
        $res = $this->pedir('POST', '/auth/registro', [
            'nombre' => 'Hugo', 'apellido' => 'Vera', 'email' => 'hvera@sigeru.uy', 'password' => 'ClaveSegura#1',
            'rol' => 'operario', 'documento' => '12345678',
        ]);
        $this->assertSame(422, $res->status);
        $this->assertArrayHasKey('documento', $res->datos()['errores']);
    }

    public function testLogoutRevocaLaSesion(): void
    {
        $token = $this->tokenDe(12);
        $cab = ['Authorization' => "Bearer $token"];
        $this->assertSame(200, $this->pedir('GET', '/auth/me', [], null, $cab)->status);
        $this->assertSame(200, $this->pedir('POST', '/auth/logout', [], null, $cab)->status);
        $this->assertSame(401, $this->pedir('GET', '/auth/me', [], null, $cab)->status);
    }

    public function testMetodoNoPermitidoDevuelve405(): void
    {
        $res = $this->pedir('GET', '/auth/login');
        $this->assertSame(405, $res->status);
        $this->assertSame('POST', $res->headers['Allow']);
    }
}
