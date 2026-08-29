<?php
/** Contadores locales de lectura y de acciones de compartir noticias. */

require_once __DIR__ . '/votos.php';

/**
 * Registra como maximo una vista por visitante, noticia y dia.
 *
 * @return array{vistas:int,nueva:bool}|null null si la noticia no existe.
 */
function registrar_vista_noticia(PDO $pdo, int $noticiaId, string $visitante): ?array
{
    if ($noticiaId <= 0 || $visitante === '') return null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT vistas FROM noticias WHERE id = ? FOR UPDATE');
        $stmt->execute([$noticiaId]);
        $vistas = $stmt->fetchColumn();
        if ($vistas === false) {
            $pdo->rollBack();
            return null;
        }

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO noticias_vistas (noticia_id, visitante, fecha)
                  VALUES (?, ?, CURRENT_DATE)'
        );
        $stmt->execute([$noticiaId, $visitante]);
        $nueva = $stmt->rowCount() === 1;

        if ($nueva) {
            $pdo->prepare('UPDATE noticias SET vistas = vistas + 1, updated_at = updated_at WHERE id = ?')
                ->execute([$noticiaId]);
            $vistas = (int) $vistas + 1;
        }

        $pdo->commit();
        return ['vistas' => (int) $vistas, 'nueva' => $nueva];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Incrementa el contador al pulsar un destino de compartir. */
function registrar_compartido_noticia(PDO $pdo, int $noticiaId, string $destino): bool
{
    if (!in_array($destino, ['facebook', 'whatsapp'], true)) return false;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM noticias WHERE id = ? FOR UPDATE');
        $stmt->execute([$noticiaId]);
        if ($stmt->fetchColumn() === false) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare(
            'UPDATE noticias
                SET compartidos = compartidos + 1, updated_at = updated_at
              WHERE id = ?'
        )->execute([$noticiaId]);
        $pdo->prepare(
            'INSERT INTO noticias_compartidos_diarios (noticia_id, fecha, destino, cantidad)
                  VALUES (?, CURRENT_DATE, ?, 1)
             ON DUPLICATE KEY UPDATE cantidad = cantidad + 1'
        )->execute([$noticiaId, $destino]);

        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
