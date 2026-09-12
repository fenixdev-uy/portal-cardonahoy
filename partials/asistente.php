<?php $asistentePublico = $asistentePublico ?? configuracion_asistente_publico(); ?>
<aside
  class="news-assistant"
  data-news-assistant
  data-endpoint="<?= e(url_portal('asistente/consultar.php')) ?>"
  data-story-endpoint="<?= e(url_portal('cargar-noticia.php')) ?>"
  aria-label="Asistente de noticias"
>
  <button
    class="news-assistant-backdrop"
    type="button"
    data-assistant-backdrop
    tabindex="-1"
    aria-label="Cerrar asistente"
  ></button>
  <section
    class="news-assistant-panel"
    id="newsAssistantPanel"
    role="dialog"
    aria-modal="false"
    aria-labelledby="newsAssistantTitle"
    aria-describedby="newsAssistantDescription"
    aria-hidden="true"
    tabindex="-1"
    hidden
  >
    <header class="news-assistant-header">
      <span class="news-assistant-handle" aria-hidden="true"></span>
      <span class="news-assistant-avatar" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 3.2 13.55 8.45 18.8 10l-5.25 1.55L12 16.8l-1.55-5.25L5.2 10l5.25-1.55L12 3.2Z" fill="currentColor"/>
          <path d="m18.2 15.2.72 2.08L21 18l-2.08.72-.72 2.08-.72-2.08L15.4 18l2.08-.72.72-2.08Z" fill="currentColor" opacity=".72"/>
        </svg>
      </span>
      <span class="news-assistant-heading">
        <strong id="newsAssistantTitle">Asistente de noticias</strong>
        <span id="newsAssistantDescription"><i aria-hidden="true"></i> Encontrá lo que querés saber</span>
      </span>
      <button class="news-assistant-close" type="button" data-assistant-close aria-label="Cerrar asistente">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <path d="m7 7 10 10M17 7 7 17"/>
        </svg>
      </button>
    </header>

    <div class="news-assistant-messages" data-assistant-messages aria-live="polite" aria-relevant="additions">
      <article class="news-assistant-message is-assistant">
        <span class="news-assistant-message-avatar" aria-hidden="true">IA</span>
        <div class="news-assistant-message-content">
          <p><?= e($asistentePublico['bienvenida']) ?></p>
          <div class="news-assistant-suggestions" aria-label="Preguntas sugeridas">
            <button type="button" data-assistant-suggestion="¿Qué noticias recientes hay?">Noticias recientes</button>
            <button type="button" data-assistant-suggestion="¿Qué se publicó sobre deportes?">Deportes</button>
            <button type="button" data-assistant-suggestion="¿Qué se publicó sobre tecnología?">Tecnología</button>
          </div>
        </div>
      </article>
    </div>

    <form class="news-assistant-form" data-assistant-form>
      <label class="sr-only" for="newsAssistantInput">Preguntá por una noticia</label>
      <textarea
        id="newsAssistantInput"
        data-assistant-input
        rows="1"
        maxlength="500"
        placeholder="Preguntá por una noticia…"
        autocomplete="off"
        enterkeyhint="send"
      ></textarea>
      <button class="news-assistant-send" type="submit" data-assistant-send aria-label="Enviar pregunta" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m4 4 16 8-16 8 2.4-8L4 4Z"/>
          <path d="M6.4 12H20"/>
        </svg>
      </button>
    </form>
    <p class="news-assistant-note">Las respuestas se basan en noticias publicadas en el portal.</p>
  </section>

  <button
    class="news-assistant-launcher"
    type="button"
    data-assistant-launcher
    aria-controls="newsAssistantPanel"
    aria-expanded="false"
  >
    <span class="news-assistant-launcher-icon" aria-hidden="true">
      <svg class="icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 14a4 4 0 0 1-4 4H9l-5 3v-7a4 4 0 0 1-1-2.65V7a4 4 0 0 1 4-4h9a4 4 0 0 1 4 4v7Z"/>
        <path d="m12 6 .72 2.28L15 9l-2.28.72L12 12l-.72-2.28L9 9l2.28-.72L12 6Z" fill="currentColor" stroke="none"/>
      </svg>
      <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="m7 7 10 10M17 7 7 17"/>
      </svg>
    </span>
    <span class="news-assistant-launcher-copy">
      <small>
        <?= e($asistentePublico['titulo']) ?>
        <svg class="news-assistant-launcher-sparkle" viewBox="0 0 16 16" aria-hidden="true">
          <path d="M8 0.8 9.35 5.65 14.2 7 9.35 8.35 8 13.2 6.65 8.35 1.8 7l4.85-1.35L8 .8Z" fill="currentColor"/>
        </svg>
      </small>
      <strong><?= e($asistentePublico['detalle']) ?></strong>
    </span>
  </button>
</aside>
