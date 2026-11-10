<?php

/**
 * Crea el primer administrador del sistema (se usa una sola vez, en la instalación):
 *   docker compose exec api-usuarios php bin/crear_admin.php
 *
 * Pide los datos por consola; la contraseña no se muestra mientras se escribe.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Clock;
use App\Core\Database;
use App\Core\Validator;

if (PHP_SAPI !== 'cli') {
    exit("Este script solo se ejecuta desde la consola.\n");
}

function preguntar(string $texto, bool $oculto = false): string
{
    if (feof(STDIN)) {
        exit("\nSe canceló el alta.\n");
    }
    echo $texto;
    if ($oculto && DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $valor = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $valor;
    }
    return trim((string) fgets(STDIN));
}

$db = Database::conexion();
echo "== SiGeRU: alta del administrador inicial ==\n";

$nombre = preguntar('Nombre: ');
$apellido = preguntar('Apellido: ');
$email = mb_strtolower(preguntar('Correo electrónico: '));
$documento = preg_replace('/\D/', '', preguntar('Cédula (ej. 1.234.567-2): '));

$errores = [];
if (mb_strlen($nombre) < 2 || mb_strlen($apellido) < 2) {
    $errores[] = 'El nombre y el apellido deben tener al menos 2 letras.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El correo no es válido.';
}
if (!Validator::cedulaValida($documento)) {
    $errores[] = 'La cédula no es válida (se controla el dígito verificador).';
}
$existe = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ? OR documento = ?');
$existe->execute([$email, $documento]);
if ($existe->fetchColumn() > 0) {
    $errores[] = 'Ya existe un usuario con ese correo o esa cédula.';
}
if ($errores) {
    exit(implode("\n", $errores) . "\n");
}

do {
    $clave = preguntar('Contraseña: ', true);
    $ok = Validator::claveSegura($clave);
    if (!$ok) {
        echo "Debe tener al menos 10 caracteres, mayúsculas, minúsculas, números y un símbolo.\n";
        continue;
    }
    if (preguntar('Repetir contraseña: ', true) !== $clave) {
        echo "Las contraseñas no coinciden.\n";
        $ok = false;
    }
} while (!$ok);

$st = $db->prepare("INSERT INTO usuarios (rol_id, nombre, apellido, documento, email, password_hash, estado, idioma, creado_en)
                    VALUES (1, ?, ?, ?, ?, ?, 'activo', 'es', ?)");
$st->execute([$nombre, $apellido, $documento, $email, password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]), Clock::ahora()]);
$id = (int) $db->lastInsertId();
$db->prepare("INSERT INTO auditoria (usuario_id, servicio, accion, entidad, entidad_id, detalle, creado_en) VALUES (?, 'consola', 'crear_admin', 'usuario', ?, 'Administrador inicial', ?)")
   ->execute([$id, (string) $id, Clock::ahora()]);

echo "Administrador creado (id $id). Ya puede ingresar al backoffice con $email.\n";
