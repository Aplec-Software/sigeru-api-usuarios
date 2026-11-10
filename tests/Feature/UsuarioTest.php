<?php

namespace Tests\Feature;

use Tests\Support\TestCase;

class UsuarioTest extends TestCase
{
    public function testSinTokenDevuelve401YSinPermisoDevuelve403(): void
    {
        $this->assertSame(401, $this->pedir('GET', '/usuarios')->status);
        $this->assertSame(403, $this->pedir('GET', '/usuarios', [], 12)->status);
    }

    public function testAdministradorListaYFiltraPorRol(): void
    {
        $res = $this->pedir('GET', '/usuarios?rol=cuadrilla&por_pagina=50', [], 1);
        $this->assertSame(200, $res->status);
        $cuerpo = $res->datos();
        foreach ($cuerpo['datos'] as $u) {
            $this->assertSame('cuadrilla', $u['rol']);
            $this->assertArrayNotHasKey('password_hash', $u);
        }
        $this->assertSame(8, $cuerpo['meta']['total']);
    }

    public function testCrearUsuarioOperarioExigeCentro(): void
    {
        $base = ['rol_id' => 3, 'nombre' => 'Elena', 'apellido' => 'Ríos', 'email' => 'erios@sigeru.uy',
                 'password' => 'ClaveSegura#1', 'documento' => '11111111'];
        $this->assertSame(422, $this->pedir('POST', '/usuarios', $base, 1)->status);
        $res = $this->pedir('POST', '/usuarios', $base + ['centro_id' => 2], 1);
        $this->assertSame(201, $res->status);
        $this->assertSame('activo', $res->datos()['datos']['estado']);
        $this->assertStringContainsString('/usuarios/', $res->headers['Location']);
    }

    public function testModificarYDarDeBaja(): void
    {
        $res = $this->pedir('PUT', '/usuarios/13', ['telefono' => '099 555 444'], 1);
        $this->assertSame(200, $res->status);
        $this->assertSame('099 555 444', $res->datos()['datos']['telefono']);

        $res = $this->pedir('DELETE', '/usuarios/13', [], 1);
        $this->assertSame(200, $res->status);
        $this->assertSame('inactivo', $this->db->query('SELECT estado FROM usuarios WHERE id = 13')->fetchColumn());
    }

    public function testUsuarioInexistenteDevuelve404(): void
    {
        $this->assertSame(404, $this->pedir('GET', '/usuarios/9999', [], 1)->status);
    }

    public function testAdministradorNoPuedeDarseDeBajaASiMismo(): void
    {
        $this->assertSame(409, $this->pedir('DELETE', '/usuarios/1', [], 1)->status);
    }

    public function testAprobarSolicitudPendienteNotificaAlUsuario(): void
    {
        $res = $this->pedir('PUT', '/usuarios/18/estado', ['estado' => 'activo'], 1);
        $this->assertSame(200, $res->status);
        $this->assertSame(1, $this->contar('notificaciones', "usuario_id = 18 AND tipo = 'cuenta_aprobada'"));
    }

    public function testDesactivarUsuarioRevocaSusSesiones(): void
    {
        $token = $this->tokenDe(14);
        $this->pedir('PUT', '/usuarios/14/estado', ['estado' => 'inactivo'], 1);
        $res = $this->pedir('GET', '/auth/me', [], null, ['Authorization' => "Bearer $token"]);
        $this->assertSame(401, $res->status);
    }
}
