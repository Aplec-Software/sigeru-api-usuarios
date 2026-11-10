# SiGeRU - API de Usuarios

Parte de **SiGeRU** (Sistema de Gestión de Residuos Urbanos), proyecto de egreso de Aplec Software (ESI, 2026).

Registro de vecinos y personal, inicio y cierre de sesión, usuarios y roles, perfil, notificaciones y parámetros del sistema. Es el único servicio que **firma** los tokens JWT.

API REST en PHP 8 con patrón MVC, sin frameworks. Responde siempre en JSON.

## Estructura

```
app/
  Core/          núcleo: router, peticiones y respuestas, validación, base de datos, JWT, idioma, log
  Controllers/   un controlador por recurso
  Models/        acceso a datos (consultas preparadas con PDO)
  Views/         JsonView: arma la respuesta y oculta campos internos
  rutas.php      todas las rutas con los roles que pueden usarlas
lang/            mensajes en español e inglés
public/          index.php (punto de entrada) y .htaccess
bin/             scripts de consola
tests/           pruebas unitarias y de funcionalidad (PHPUnit)
```

## Formato de las respuestas

```json
{"status": "ok", "datos": [ ], "meta": {"total": 48, "pagina": 1, "por_pagina": 15, "paginas": 4}}
{"status": "error", "mensaje": "Hay datos inválidos en la solicitud.", "errores": {"email": "..."}}
```

Códigos usados: 200, 201, 401 (sin sesión), 403 (sin permiso), 404, 405, 409 (conflicto con una regla de negocio),
422 (datos inválidos), 429 (demasiados intentos) y 500.

## Autenticación

El token JWT (RS256) llega en la cookie HttpOnly `sigeru_token` (navegador) o en `Authorization: Bearer <token>`
(Postman, pruebas). Si viene en la cookie, las operaciones que modifican datos deben traer la cabecera
`X-Requested-With: SiGeRU` (protección CSRF). Además de la firma se controla que la sesión no haya sido revocada.

## Variables de entorno

| Variable | Para qué sirve | Ejemplo |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME` | Servidor MySQL | `192.168.56.20`, `3306`, `sigeru` |
| `DB_USER`, `DB_PASS` | Usuario de MySQL propio de este servicio (permisos mínimos) | `api_usuarios` |
| `JWT_CLAVE_PUBLICA` | Clave pública para verificar los tokens | `/claves/jwt_publica.pem` |
| `JWT_CLAVE_PRIVADA` | Clave privada RSA para firmar los tokens (solo esta API la tiene) | `/claves/jwt_privada.pem` |
| `COOKIE_SEGURA` | `1` para marcar la cookie de sesión como Secure (HTTPS) | `1` |
| `APP_IDIOMA` | Idioma por defecto de los mensajes (`es` o `en`) | `es` |
| `CONFIAR_PROXY` | `1` si está detrás del gateway (toma la IP de `X-Forwarded-For`) | `1` |
| `LOG_ARCHIVO` | Archivo de log; `{fecha}` genera uno por día | `/var/log/sigeru/api-usuarios-{fecha}.log` |
| `BASE_PATH` | Prefijo de la ruta si el gateway no lo quita (en Docker queda vacío) | |
| `DB_DRIVER=sqlite`, `DB_RUTA` | Solo desarrollo: usar un archivo SQLite en lugar de MySQL | |

## Pruebas

Las pruebas crean una base SQLite en memoria con la misma estructura y los datos de prueba, así que no hace falta MySQL:

```bash
composer install
composer test
```

## Administrador inicial

En una instalación nueva no hay usuarios. El primer administrador se crea por consola:

```bash
docker compose exec api-usuarios php bin/crear_admin.php
```

Pide nombre, correo, cédula y contraseña (la contraseña no se ve al escribirla y tiene que cumplir la política).

## Rutas

Todas se acceden a través del gateway con el prefijo `/api/usuarios` (por ejemplo `https://192.168.56.10/api/usuarios/salud`).

| Método | Ruta | Controlador | Acceso |
|---|---|---|---|
| GET | `/salud` | Salud::index | pública |
| POST | `/auth/login` | Auth::login | pública |
| POST | `/auth/registro` | Auth::registro | pública |
| POST | `/auth/logout` | Auth::logout | cualquier usuario con sesión |
| GET | `/auth/me` | Auth::me | cualquier usuario con sesión |
| PUT | `/perfil` | Perfil::update | cualquier usuario con sesión |
| PUT | `/perfil/clave` | Perfil::cambiarClave | cualquier usuario con sesión |
| GET | `/usuarios` | Usuario::index | administrador |
| POST | `/usuarios` | Usuario::store | administrador |
| GET | `/usuarios/{id}` | Usuario::show | administrador |
| PUT | `/usuarios/{id}` | Usuario::update | administrador |
| DELETE | `/usuarios/{id}` | Usuario::destroy | administrador |
| PUT | `/usuarios/{id}/estado` | Usuario::estado | administrador |
| GET | `/roles` | Rol::index | pública |
| GET | `/notificaciones` | Notificacion::index | cualquier usuario con sesión |
| PUT | `/notificaciones` | Notificacion::marcarTodas | cualquier usuario con sesión |
| PUT | `/notificaciones/{id}` | Notificacion::marcar | cualquier usuario con sesión |
| DELETE | `/notificaciones/{id}` | Notificacion::destroy | cualquier usuario con sesión |
| GET | `/parametros` | Parametro::index | administrador |
| GET | `/parametros/publicos` | Parametro::publicos | pública |
| PUT | `/parametros/{clave:[a-z_]+}` | Parametro::update | administrador |

## Docker

La imagen fija la versión de PHP con `ARG PHP_VERSION=8.3.36` (`FROM php:${PHP_VERSION}-apache`), la primera que corrige
las vulnerabilidades «php2shell» (CVE-2026-109618 y CVE-2026-109619, octubre de 2026). El `php.ini` de producción incluye
`session.upload_progress.enabled = 0`, la mitigación que recomendó PHP para CVE-2026-115560 hasta su parche. Para
actualizar a una versión nueva de PHP: cambiar `PHP_VERSION`, reconstruir con `docker compose build --pull` y subir
`PHP_MINIMO` en el servidor (ver `sigeru-infra`).


Normalmente se levanta junto con el resto desde el repositorio `sigeru-infra` (`docker compose up -d --build`).
Para construir solo esta imagen: `docker build -t aplec/sigeru-api-usuarios .`
