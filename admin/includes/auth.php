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
    session_name('portal_noticias_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
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

    try {
        $stmt = db()->prepare(
            'SELECT u.id, u.nombre, u.email, u.activo, u.debe_cambiar_password,
                    r.id AS rol_id, r.nombre AS rol_nombre, r.slug AS rol_slug
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ? AND u.activo = 1'
        );
        $stmt->execute([$id]);
        $usuario = $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        $usuario = null;
    }

    if (!$usuario) {
        cerrar_sesion();
    }

    $cache[$id] = $usuario;
    return $usuario;
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

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sesion vencida. Volve a ingresar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . ruta_login());
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
    $_SESSION = [
        'usuario_id' => $usuarioId,
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
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], true);
    }
    session_destroy();
}
