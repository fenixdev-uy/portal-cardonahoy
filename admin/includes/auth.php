<?php
/**
 * Autenticacion, autorizacion por roles y proteccion CSRF del panel.
 */

require_once __DIR__ . '/../config.php';

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $segura = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_name(portal_cookie_name('noticias_admin'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => portal_cookie_path(),
        'domain' => '',
        'secure' => $segura,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** @return array<string,mixed>|null */
function usuario_actual(): ?array
{
    iniciar_sesion_segura();
    $id = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $ahora = time();
    $inactiva = $ahora - (int) ($_SESSION['ultima_actividad'] ?? $ahora) > 2 * 3600;
    $demasiadoLarga = $ahora - (int) ($_SESSION['iniciada_en'] ?? $ahora) > 12 * 3600;
    if ($inactiva || $demasiadoLarga) {
        cerrar_sesion();
        return null;
    }
    $_SESSION['ultima_actividad'] = $ahora;

    static $cache = [];
    if (array_key_exists($id, $cache)) {
        return $cache[$id];
    }

    $sesionReemplazada = false;
    try {
        $stmt = db()->prepare(
            'SELECT u.id, u.nombre, u.email, u.foto, u.activo, u.debe_cambiar_password,
                    u.sesion_token_hash,
                    r.id AS rol_id, r.nombre AS rol_nombre, r.slug AS rol_slug
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ? AND u.activo = 1'
        );
        $stmt->execute([$id]);
        $usuario = $stmt->fetch() ?: null;

        $tokenSesion = (string) ($_SESSION['sesion_token'] ?? '');
        $tokenHash = (string) ($usuario['sesion_token_hash'] ?? '');
        $sesionReemplazada = $usuario && $tokenSesion !== '' && $tokenHash !== ''
            && !hash_equals($tokenHash, hash('sha256', $tokenSesion));
        if ($usuario && ($tokenSesion === '' || $tokenHash === '' || $sesionReemplazada)) {
            $usuario = null;
        }
        if ($usuario) {
            unset($usuario['sesion_token_hash']);
        }
    } catch (PDOException $e) {
        $usuario = null;
    }

    if (!$usuario) {
        cerrar_sesion();
        if ($sesionReemplazada) {
            $GLOBALS['portal_sesion_reemplazada'] = true;
        }
    }

    $cache[$id] = $usuario;
    return $usuario;
}

/** Consulta la sesión desde el portal público sin crear una nueva para visitantes anónimos. */
function usuario_actual_publico(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE
        && empty($_COOKIE[portal_cookie_name('noticias_admin')])) {
        return null;
    }
    return usuario_actual();
}

function ruta_login(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return str_contains($script, '/admin/includes/') ? '../login.php' : 'login.php';
}

function exigir_login(bool $json = false): array
{
    $usuario = usuario_actual();
    if ($usuario) {
        if (function_exists('configuracion_mantenimiento')
            && configuracion_mantenimiento()['activo']
            && !rol_tiene_permiso((int) ($usuario['rol_id'] ?? 0), 'mantenimiento.gestionar')) {
            cerrar_sesion();
            if ($json) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Tu rol no puede ingresar durante el mantenimiento.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            http_response_code(403);
            exit('Tu rol no tiene permiso para ingresar mientras el portal está en mantenimiento.');
        }
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!empty($usuario['debe_cambiar_password']) && !in_array($script, ['cambiar-password.php', 'logout.php'], true)) {
            if ($json) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Primero debes cambiar tu contrasena temporal.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: cambiar-password.php');
            exit;
        }
        return $usuario;
    }

    $sesionReemplazada = !empty($GLOBALS['portal_sesion_reemplazada']);
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => $sesionReemplazada
                ? 'Tu sesión se cerró porque se inició sesión con este usuario en otro dispositivo.'
                : 'Sesion vencida. Volve a ingresar.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . ruta_login() . ($sesionReemplazada ? '?motivo=sesion-reemplazada' : ''));
    exit;
}

function tiene_permiso(string $clave): bool
{
    $usuario = usuario_actual();
    if (!$usuario) {
        return false;
    }

    static $cache = [];
    $usuarioId = (int) $usuario['id'];
    if (!isset($cache[$usuarioId])) {
        $stmt = db()->prepare(
            'SELECT p.clave
               FROM rol_permisos rp
               JOIN permisos p ON p.id = rp.permiso_id
              WHERE rp.rol_id = ?'
        );
        $stmt->execute([(int) $usuario['rol_id']]);
        $cache[$usuarioId] = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    }

    return isset($cache[$usuarioId][$clave]);
}

/** Comprueba un permiso directamente sobre un rol, sin depender de una sesión. */
function rol_tiene_permiso(int $rolId, string $clave): bool
{
    if ($rolId <= 0 || $clave === '') {
        return false;
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1
               FROM rol_permisos rp
               JOIN permisos p ON p.id = rp.permiso_id
              WHERE rp.rol_id = ? AND p.clave = ?
              LIMIT 1'
        );
        $stmt->execute([$rolId, $clave]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function exigir_permiso(string $clave, bool $json = false): void
{
    exigir_login($json);
    if (tiene_permiso($clave)) {
        return;
    }

    if ($json) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No tenes permiso para realizar esta accion.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(403);
    exit('No tenes permiso para acceder a esta seccion.');
}

function csrf_token(): string
{
    iniciar_sesion_segura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verificar_csrf(bool $json = false): void
{
    iniciar_sesion_segura();
    $recibido = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $valido = $recibido !== '' && !empty($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $recibido);

    if ($valido) {
        return;
    }

    if ($json) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'La sesion del formulario vencio. Recarga la pagina.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(419);
    exit('La sesion del formulario vencio. Volve atras, recarga la pagina e intenta nuevamente.');
}

function iniciar_sesion_usuario(int $usuarioId): void
{
    iniciar_sesion_segura();
    session_regenerate_id(true);
    $tokenSesion = bin2hex(random_bytes(32));
    $stmt = db()->prepare('UPDATE usuarios SET sesion_token_hash = ? WHERE id = ? AND activo = 1');
    $stmt->execute([hash('sha256', $tokenSesion), $usuarioId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('No se pudo iniciar la sesión del usuario.');
    }
    $_SESSION = [
        'usuario_id' => $usuarioId,
        'sesion_token' => $tokenSesion,
        'csrf_token' => bin2hex(random_bytes(32)),
        'iniciada_en' => time(),
        'ultima_actividad' => time(),
    ];
}

function cerrar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    $tokenSesion = (string) ($_SESSION['sesion_token'] ?? '');
    if ($usuarioId > 0 && $tokenSesion !== '') {
        try {
            $stmt = db()->prepare(
                'UPDATE usuarios SET sesion_token_hash = NULL
                  WHERE id = ? AND sesion_token_hash = ?'
            );
            $stmt->execute([$usuarioId, hash('sha256', $tokenSesion)]);
        } catch (PDOException $e) {
            // La sesión local igualmente debe destruirse si la base no responde.
        }
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], true);
    }
    session_destroy();
}
