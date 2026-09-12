<?php
/** Historial de conversaciones del asistente público. */

require_once __DIR__ . '/includes/funciones.php';
exigir_permiso('asistente.ver');

$pdo = db();
$porPagina = 30;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$conversacionSolicitada = max(0, (int) ($_GET['id'] ?? 0));
$detalleSolicitadoMobile = $conversacionSolicitada > 0;
$totalConversaciones = 0;
$totalPaginas = 1;
$conversaciones = [];
$conversacionActiva = null;
$mensajes = [];
$historialDisponible = asistente_historial_disponible($pdo);

$formatearFecha = static function (?string $valor, bool $incluirAnio = true): string {
    if (!$valor) return 'Sin fecha';
    try {
        $fecha = new DateTimeImmutable($valor);
        return $fecha->format($incluirAnio ? 'd/m/Y · H:i' : 'd/m · H:i');
    } catch (Throwable $e) {
        return 'Sin fecha';
    }
};

if ($historialDisponible) {
    $totalConversaciones = (int) $pdo->query('SELECT COUNT(*) FROM asistente_conversaciones')->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalConversaciones / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $stmt = $pdo->prepare(
        'SELECT id, pregunta_inicial, cantidad_mensajes, created_at, ultimo_mensaje_at
           FROM asistente_conversaciones
          ORDER BY ultimo_mensaje_at DESC, id DESC
          LIMIT :limite OFFSET :offset'
    );
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $conversaciones = $stmt->fetchAll();

    if ($conversacionSolicitada <= 0 && $conversaciones !== []) {
        $conversacionSolicitada = (int) $conversaciones[0]['id'];
    }
    if ($conversacionSolicitada > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, pregunta_inicial, cantidad_mensajes, created_at, ultimo_mensaje_at
               FROM asistente_conversaciones WHERE id = ?'
        );
        $stmt->execute([$conversacionSolicitada]);
        $conversacionActiva = $stmt->fetch() ?: null;
        if ($conversacionActiva) {
            $stmt = $pdo->prepare(
                'SELECT id, rol, contenido, noticias_json, modo, created_at
                   FROM asistente_mensajes
                  WHERE conversacion_id = ?
                  ORDER BY id ASC'
            );
            $stmt->execute([(int) $conversacionActiva['id']]);
            $mensajes = $stmt->fetchAll();
        }
    }
}

$titulo = 'Asistente';
$active = 'asistente';
require __DIR__ . '/includes/header.php';
?>
    <div class="assistant-admin-page<?= $detalleSolicitadoMobile ? ' is-mobile-detail' : '' ?>">
      <div class="users-page-heading assistant-admin-heading">
        <div>
          <span class="assistant-admin-eyebrow">Conversaciones públicas</span>
          <h1>Asistente</h1>
          <p>Revisá las preguntas de los lectores y las respuestas que recibieron, en el orden exacto de cada conversación.</p>
        </div>
        <div class="assistant-admin-count" aria-label="Total de conversaciones">
          <strong><?= (int) $totalConversaciones ?></strong>
          <span><?= $totalConversaciones === 1 ? 'conversación' : 'conversaciones' ?></span>
        </div>
      </div>

<?php if (!$historialDisponible): ?>
      <section class="users-panel assistant-admin-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="7" width="16" height="13" rx="3"></rect><path d="M9 11h.01M15 11h.01M8 16h8M12 7V3M9 3h6"></path></svg>
        <h2>El historial todavía no está habilitado</h2>
        <p>Aplicá la migración del historial del asistente en este entorno para comenzar a registrar conversaciones.</p>
      </section>
<?php elseif ($totalConversaciones < 1): ?>
      <section class="users-panel assistant-admin-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path><path d="M8 9h8M8 13h5"></path></svg>
        <h2>Todavía no hay conversaciones</h2>
        <p>Cuando un lector use el asistente público, su conversación aparecerá aquí automáticamente.</p>
      </section>
<?php else: ?>
      <section class="assistant-admin-layout<?= $detalleSolicitadoMobile ? ' is-mobile-detail' : '' ?>" aria-label="Historial del asistente">
        <aside class="assistant-history-panel" aria-label="Conversaciones">
          <div class="assistant-history-head">
            <div>
              <h2>Historial</h2>
              <p>Más recientes primero</p>
            </div>
            <span><?= (int) $totalConversaciones ?></span>
          </div>

          <div class="assistant-history-list">
<?php foreach ($conversaciones as $conversacion):
    $idConversacion = (int) $conversacion['id'];
    $activa = $conversacionActiva && $idConversacion === (int) $conversacionActiva['id'];
?>
            <a class="assistant-history-item<?= $activa ? ' is-active' : '' ?>" href="asistente.php?id=<?= $idConversacion ?>&amp;pagina=<?= $pagina ?>"<?= $activa ? ' aria-current="page"' : '' ?>>
              <span class="assistant-history-time">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                <?= e($formatearFecha((string) $conversacion['ultimo_mensaje_at'])) ?>
              </span>
              <strong><?= e((string) $conversacion['pregunta_inicial']) ?></strong>
              <small><?= (int) $conversacion['cantidad_mensajes'] ?> mensajes</small>
            </a>
<?php endforeach; ?>
          </div>

<?php if ($totalPaginas > 1): ?>
          <nav class="assistant-history-pagination" aria-label="Páginas del historial">
            <?php if ($pagina > 1): ?><a href="asistente.php?pagina=<?= $pagina - 1 ?>" rel="prev">← Anterior</a><?php else: ?><span></span><?php endif; ?>
            <span><?= $pagina ?> de <?= $totalPaginas ?></span>
            <?php if ($pagina < $totalPaginas): ?><a href="asistente.php?pagina=<?= $pagina + 1 ?>" rel="next">Siguiente →</a><?php else: ?><span></span><?php endif; ?>
          </nav>
<?php endif; ?>
        </aside>

        <article class="assistant-conversation-panel">
<?php if (!$conversacionActiva): ?>
          <div class="assistant-conversation-empty">
            <p>Seleccioná una conversación del historial para ver su contenido.</p>
          </div>
<?php else: ?>
          <header class="assistant-conversation-head">
            <a class="assistant-mobile-back" href="asistente.php?pagina=<?= $pagina ?>" aria-label="Volver al historial de conversaciones">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
              Historial
            </a>
            <div>
              <span>Conversación #<?= (int) $conversacionActiva['id'] ?></span>
              <h2><?= e((string) $conversacionActiva['pregunta_inicial']) ?></h2>
            </div>
            <time datetime="<?= e(str_replace(' ', 'T', (string) $conversacionActiva['created_at'])) ?>"><?= e($formatearFecha((string) $conversacionActiva['created_at'])) ?></time>
          </header>

          <div class="assistant-conversation-messages">
<?php foreach ($mensajes as $mensaje):
    $esAsistente = (string) $mensaje['rol'] === 'assistant';
    $noticias = [];
    if ($esAsistente && trim((string) ($mensaje['noticias_json'] ?? '')) !== '') {
        $decodificadas = json_decode((string) $mensaje['noticias_json'], true);
        if (is_array($decodificadas)) $noticias = $decodificadas;
    }
?>
            <section class="assistant-transcript-message <?= $esAsistente ? 'is-assistant' : 'is-user' ?>">
              <div class="assistant-transcript-meta">
                <span><?= $esAsistente ? 'IA' : 'Lector' ?></span>
                <time datetime="<?= e(str_replace(' ', 'T', (string) $mensaje['created_at'])) ?>"><?= e($formatearFecha((string) $mensaje['created_at'], false)) ?></time>
              </div>
              <div class="assistant-transcript-bubble"><p><?= nl2br(e((string) $mensaje['contenido'])) ?></p></div>
<?php if ($noticias !== []): ?>
              <div class="assistant-transcript-news" aria-label="Noticias mostradas en esta respuesta">
<?php foreach ($noticias as $noticia):
    if (!is_array($noticia)) continue;
    $url = trim((string) ($noticia['url'] ?? ''));
    $tituloNoticia = trim((string) ($noticia['titulo'] ?? ''));
    if ($url === '' || $tituloNoticia === '') continue;
?>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">
<?php if (trim((string) ($noticia['imagen'] ?? '')) !== ''): ?>
                  <img src="<?= e((string) $noticia['imagen']) ?>" alt="" loading="lazy">
<?php else: ?>
                  <span class="assistant-transcript-news-placeholder" aria-hidden="true"></span>
<?php endif; ?>
                  <span><strong><?= e($tituloNoticia) ?></strong><small>Abrir noticia ↗</small></span>
                </a>
<?php endforeach; ?>
              </div>
<?php endif; ?>
            </section>
<?php endforeach; ?>
          </div>
<?php endif; ?>
        </article>
      </section>
<?php endif; ?>
    </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
