<?php

namespace Tests\Feature;

use Tests\Support\TestCase;

class PerfilNotificacionParametroTest extends TestCase
{
    public function testActualizarPerfilPropio(): void
    {
        $res = $this->pedir('PUT', '/perfil', ['idioma' => 'en'], 12);
        $this->assertSame(200, $res->status);
        $this->assertSame('en', $res->datos()['datos']['idioma']);
    }

    public function testCambiarClaveVerificaLaActual(): void
    {
        $this->assertSame(422, $this->pedir('PUT', '/perfil/clave', ['actual' => 'Otra2026!!', 'nueva' => 'NuevaClave#2026'], 12)->status);
        $this->assertSame(200, $this->pedir('PUT', '/perfil/clave', ['actual' => 'Sigeru2026!', 'nueva' => 'NuevaClave#2026'], 12)->status);
        $res = $this->pedir('POST', '/auth/login', ['email' => 'ana.martinez@correo.uy', 'password' => 'NuevaClave#2026']);
        $this->assertSame(200, $res->status);
    }

    public function testNotificacionesPropiasYAjenas(): void
    {
        $res = $this->pedir('GET', '/notificaciones', [], 1);
        $this->assertSame(200, $res->status);
        $this->assertSame(2, $res->datos()['meta']['no_leidas']);
        $this->assertSame(200, $this->pedir('PUT', '/notificaciones/1', [], 1)->status);
        // la notificación 4 es de otro usuario: no se puede tocar
        $this->assertSame(404, $this->pedir('PUT', '/notificaciones/4', [], 1)->status);
        $res = $this->pedir('PUT', '/notificaciones', [], 1);
        $this->assertSame(0, $res->datos()['meta']['no_leidas']);
    }

    public function testParametrosPublicosYModificacion(): void
    {
        $res = $this->pedir('GET', '/parametros/publicos');
        $this->assertSame(200, $res->status);
        $this->assertArrayHasKey('idioma_por_defecto', $res->datos()['datos']);
        $this->assertArrayNotHasKey('login_max_intentos', $res->datos()['datos']);

        $this->assertSame(422, $this->pedir('PUT', '/parametros/login_max_intentos', ['valor' => 'muchos'], 1)->status);
        $this->assertSame(200, $this->pedir('PUT', '/parametros/login_max_intentos', ['valor' => 3], 1)->status);
        $this->assertSame(403, $this->pedir('PUT', '/parametros/login_max_intentos', ['valor' => 3], 3)->status);
    }

    public function testSaludDeLaApi(): void
    {
        $res = $this->pedir('GET', '/salud');
        $this->assertSame(200, $res->status);
        $this->assertSame('ok', $res->datos()['datos']['bd']);
    }
}
