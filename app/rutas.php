<?php

/**
 * Rutas de la API de Usuarios.
 * null = pública, [] = cualquier usuario autenticado, [roles] = solo esos roles.
 */

use App\Controllers\AuthController;
use App\Controllers\NotificacionController;
use App\Controllers\ParametroController;
use App\Controllers\PerfilController;
use App\Controllers\RolController;
use App\Controllers\SaludController;
use App\Controllers\UsuarioController;
use App\Core\Router;

$r = new Router();
$admin = ['administrador'];

$r->get('/salud', [SaludController::class, 'index'], null);

// Autenticación y registro
$r->post('/auth/login', [AuthController::class, 'login'], null);
$r->post('/auth/registro', [AuthController::class, 'registro'], null);
$r->post('/auth/logout', [AuthController::class, 'logout'], []);
$r->get('/auth/me', [AuthController::class, 'me'], []);

// Perfil propio
$r->put('/perfil', [PerfilController::class, 'update'], []);
$r->put('/perfil/clave', [PerfilController::class, 'cambiarClave'], []);

// ABM de usuarios (todos los roles)
$r->recurso('/usuarios', UsuarioController::class, $admin, $admin);
$r->put('/usuarios/{id}/estado', [UsuarioController::class, 'estado'], $admin);
$r->get('/roles', [RolController::class, 'index'], null);

// Notificaciones del usuario autenticado
$r->get('/notificaciones', [NotificacionController::class, 'index'], []);
$r->put('/notificaciones', [NotificacionController::class, 'marcarTodas'], []);
$r->put('/notificaciones/{id}', [NotificacionController::class, 'marcar'], []);
$r->delete('/notificaciones/{id}', [NotificacionController::class, 'destroy'], []);

// Parámetros del sistema
$r->get('/parametros/publicos', [ParametroController::class, 'publicos'], null);
$r->get('/parametros', [ParametroController::class, 'index'], $admin);
$r->put('/parametros/{clave:[a-z_]+}', [ParametroController::class, 'update'], $admin);

return $r;
