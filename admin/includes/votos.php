<?php
/**
 * Votos del front (me gusta / no me gusta).
 *
 * Regla de negocio aprobada: un voto por visitante y por noticia, definitivo.
 * No se deshace ni se cambia, por lo que los contadores de "noticias" solo se
 * incrementan y nunca pueden quedar por debajo de cero.
 *
 * La verdad vive en "noticias_votos"; las columnas "me_gusta" y "no_me_gusta"
 * son un cache de lectura para que el feed no tenga que agrupar en cada carga.
 */

require_once __DIR__ . '/../config.php';

const VOTOS_COOKIE_DIAS = 365;
const VOTOS_LIMITE_POR_VENTANA = 60;
const VOTOS_VENTANA_SEGUNDOS = 3600;

/**
 * Indica si la conexion actual es HTTPS (mismo criterio que el panel).
 */
function votos_conexion_segura(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Genera un UUID v4.
 */
function votos_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * Devuelve el identificador del visitante guardado en la cookie.
 *
 * Con $crear en true emite la cookie cuando todavia no existe o es invalida.
 * Devuelve '' si no hay identificador y no se pidio crearlo.
 */
function visitante_id(bool $crear = false): string
{
    static $cache = null;
    if ($cache !== null && !($crear && $cache === '')) {
        return $cache;
    }

    $cookieNombre = portal_cookie_name('visitante');
    $actual = (string) ($_COOKIE[$cookieNombre] ?? '');
    $valido = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $actual);

    if ($valido) {
        return $cache = $actual;
    }

    if (!$crear) {
        return $cache = '';
    }

    $nuevo = votos_uuid();
    // httponly: el identificador no necesita leerse desde JavaScript.
    setcookie($cookieNombre, $nuevo, [
        'expires' => time() + VOTOS_COOKIE_DIAS * 86400,
        'path' => portal_cookie_path(),
        'secure' => votos_conexion_segura(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$cookieNombre] = $nuevo;

    return $cache = $nuevo;
}

/**
 * Votos ya emitidos por un visitante, para marcar los botones al renderizar.
 *
 * @return array<int, int> noticia_id => valor (1 o -1)
 */
function votos_del_visitante(PDO $pdo, string $visitante): array
{
    if ($visitante === '') {
        return [];
    }

    $stmt = $pdo->prepare('SELECT noticia_id, valor FROM noticias_votos WHERE visitante = ?');
    $stmt->execute([$visitante]);

    $votos = [];
    foreach ($stmt as $fila) {
        $votos[(int) $fila['noticia_id']] = (int) $fila['valor'];
    }

    return $votos;
}

/**
 * Aplica el techo de votos por IP. Devuelve true si ya se excedio.
 *
 * La cookie no protege contra peticiones directas, asi que el limite real es
 * este. Se guarda solo el hash de la IP, no la IP.
 */
function voto_limite_excedido(PDO $pdo, string $ip): bool
{
    if ($ip === '') {
        return false;
    }

    $clave = hash('sha256', 'voto|' . $ip);

    $pdo->prepare(
        'INSERT INTO votos_limite (clave_hash, intentos, ventana_inicio_at)
              VALUES (?, 1, NOW())
         ON DUPLICATE KEY UPDATE
              intentos = IF(ventana_inicio_at < (NOW() - INTERVAL ? SECOND), 1, intentos + 1),
              ventana_inicio_at = IF(ventana_inicio_at < (NOW() - INTERVAL ? SECOND), NOW(), ventana_inicio_at)'
    )->execute([$clave, VOTOS_VENTANA_SEGUNDOS, VOTOS_VENTANA_SEGUNDOS]);

    $stmt = $pdo->prepare('SELECT intentos FROM votos_limite WHERE clave_hash = ?');
    $stmt->execute([$clave]);
    $intentos = (int) $stmt->fetchColumn();

    // Poda ocasional de ventanas viejas para que la tabla no crezca sin freno.
    if (random_int(1, 50) === 1) {
        $pdo->exec('DELETE FROM votos_limite WHERE ventana_inicio_at < (NOW() - INTERVAL 1 DAY)');
    }

    return $intentos > VOTOS_LIMITE_POR_VENTANA;
}

/**
 * Registra un voto definitivo y devuelve el estado resultante de la noticia.
 *
 * Si el visitante ya habia votado esa noticia, no altera nada y devuelve el
 * estado actual con el voto previo. Nunca decrementa un contador.
 *
 * @return array{me_gusta:int, no_me_gusta:int, mi_voto:int, nuevo:bool}|null
 *         null si la noticia no existe.
 */
function registrar_voto(PDO $pdo, int $noticiaId, int $valor, string $visitante): ?array
{
    if ($valor !== 1 && $valor !== -1) {
        return null;
    }

    $columna = $valor === 1 ? 'me_gusta' : 'no_me_gusta';
    $nuevo = false;

    $pdo->beginTransaction();
    try {
        // Se bloquea la fila para que dos votos simultaneos no se pisen.
        $stmt = $pdo->prepare('SELECT id FROM noticias WHERE id = ? FOR UPDATE');
        $stmt->execute([$noticiaId]);
        if ($stmt->fetchColumn() === false) {
            $pdo->rollBack();
            return null;
        }

        $stmt = $pdo->prepare('SELECT valor FROM noticias_votos WHERE noticia_id = ? AND visitante = ?');
        $stmt->execute([$noticiaId, $visitante]);
        $previo = $stmt->fetchColumn();

        if ($previo === false) {
            $pdo->prepare('INSERT INTO noticias_votos (noticia_id, visitante, valor) VALUES (?, ?, ?)')
                ->execute([$noticiaId, $visitante, $valor]);

            // updated_at = updated_at evita que votar cuente como una edicion.
            $pdo->prepare("UPDATE noticias SET $columna = $columna + 1, updated_at = updated_at WHERE id = ?")
                ->execute([$noticiaId]);

            $nuevo = true;
        } else {
            $valor = (int) $previo;
        }

        $stmt = $pdo->prepare('SELECT me_gusta, no_me_gusta FROM noticias WHERE id = ?');
        $stmt->execute([$noticiaId]);
        $totales = $stmt->fetch();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'me_gusta' => (int) $totales['me_gusta'],
        'no_me_gusta' => (int) $totales['no_me_gusta'],
        'mi_voto' => $valor,
        'nuevo' => $nuevo,
    ];
}
