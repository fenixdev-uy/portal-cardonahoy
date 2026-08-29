<?php
/** Actualiza exclusivamente el perfil del usuario autenticado. */

require_once __DIR__ . '/includes/funciones.php';

$usuario = exigir_login(true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

verificar_csrf(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$pdo = db();
$usuarioId = (int) $usuario['id'];
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
$passwordActual = (string) ($_POST['password_actual'] ?? '');
$passwordNueva = (string) ($_POST['password_nueva'] ?? '');
$passwordRepetida = (string) ($_POST['password_repetida'] ?? '');
$cambiarPassword = $passwordActual !== '' || $passwordNueva !== '' || $passwordRepetida !== '';
$errores = [];

if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
if (mb_strlen($nombre, 'UTF-8') > 120) $errores[] = 'El nombre no puede superar los 120 caracteres.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingresá un correo válido.';
if (mb_strlen($email, 'UTF-8') > 190) $errores[] = 'El correo no puede superar los 190 caracteres.';

$stmt = $pdo->prepare('SELECT nombre,email,password_hash,foto FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1');
$stmt->execute([$usuarioId]);
$perfilActual = $stmt->fetch();
if (!$perfilActual) {
    http_response_code(404);
    echo json_encode(['error' => 'No encontramos el perfil activo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?');
$stmt->execute([$email, $usuarioId]);
if ((int) $stmt->fetchColumn() > 0) $errores[] = 'Ya existe un usuario con ese correo.';

if ($cambiarPassword) {
    if ($passwordActual === '') $errores[] = 'Ingresá tu contraseña actual para cambiarla.';
    elseif (!password_verify($passwordActual, (string) $perfilActual['password_hash'])) $errores[] = 'La contraseña actual no es correcta.';
    if (strlen($passwordNueva) < 12) $errores[] = 'La nueva contraseña debe tener al menos 12 caracteres.';
    if ($passwordNueva !== $passwordRepetida) $errores[] = 'Las nuevas contraseñas no coinciden.';
}

if ($errores) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errores), 'errores' => $errores], JSON_UNESCAPED_UNICODE);
    exit;
}

$fotoNueva = null;
try {
    $fotoNueva = subir_imagen_usuario($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$fotoAnterior = trim((string) ($perfilActual['foto'] ?? ''));
$fotoFinal = $fotoNueva ?? $fotoAnterior;
try {
    $pdo->beginTransaction();
    if ($cambiarPassword) {
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?,email=?,foto=?,password_hash=?,debe_cambiar_password=0 WHERE id=?');
        $stmt->execute([$nombre, $email, $fotoFinal ?: null, password_hash($passwordNueva, PASSWORD_DEFAULT), $usuarioId]);
    } else {
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?,email=?,foto=? WHERE id=?');
        $stmt->execute([$nombre, $email, $fotoFinal ?: null, $usuarioId]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($fotoNueva !== null) eliminar_imagen_usuario($fotoNueva);
    http_response_code(500);
    echo json_encode(['error' => 'No pudimos guardar el perfil. Intentá nuevamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($fotoNueva !== null && $fotoAnterior !== '' && $fotoAnterior !== $fotoNueva) {
    eliminar_imagen_usuario($fotoAnterior);
}

echo json_encode([
    'ok' => true,
    'mensaje' => $cambiarPassword ? 'Perfil y contraseña actualizados.' : 'Perfil actualizado correctamente.',
    'perfil' => [
        'nombre' => $nombre,
        'email' => $email,
        'foto_url' => $fotoFinal !== '' ? url_imagen($fotoFinal) . '?v=' . time() : '',
        'inicial' => mb_strtoupper(mb_substr($nombre ?: 'U', 0, 1, 'UTF-8'), 'UTF-8'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
