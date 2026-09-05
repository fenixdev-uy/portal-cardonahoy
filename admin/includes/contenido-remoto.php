<?php
/**
 * Descarga y extrae texto periodístico desde una URL pública.
 * No sigue destinos privados ni entrega el HTML remoto al navegador.
 */

if (PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

final class ContenidoRemotoException extends RuntimeException
{
    public function __construct(string $mensaje, public readonly int $estadoHttp = 422)
    {
        parent::__construct($mensaje);
    }
}

function ip_en_cidr_contenido_remoto(string $ip, string $cidr): bool
{
    [$red, $prefijo] = explode('/', $cidr, 2);
    $binarioIp = @inet_pton($ip);
    $binarioRed = @inet_pton($red);
    if ($binarioIp === false || $binarioRed === false || strlen($binarioIp) !== strlen($binarioRed)) return false;
    $bits = (int) $prefijo;
    $bytesCompletos = intdiv($bits, 8);
    $bitsRestantes = $bits % 8;
    if ($bytesCompletos > 0 && substr($binarioIp, 0, $bytesCompletos) !== substr($binarioRed, 0, $bytesCompletos)) {
        return false;
    }
    if ($bitsRestantes === 0) return true;
    $mascara = (0xff << (8 - $bitsRestantes)) & 0xff;
    return (ord($binarioIp[$bytesCompletos]) & $mascara) === (ord($binarioRed[$bytesCompletos]) & $mascara);
}

function ip_publica_contenido_remoto(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
    $rangosBloqueados = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
        '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b:1::/48', '100::/64',
        '2001::/23', '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10',
        'fec0::/10', 'ff00::/8',
    ];
    foreach ($rangosBloqueados as $cidr) {
        if (ip_en_cidr_contenido_remoto($ip, $cidr)) return false;
    }
    return true;
}

/** @return array{url:string,host:string,port:int,ip:string} */
function validar_url_publica_contenido_remoto(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new ContenidoRemotoException('Ingresá una URL válida y completa, por ejemplo https://sitio.com/noticia.');
    }

    $partes = parse_url($url);
    $esquema = strtolower((string) ($partes['scheme'] ?? ''));
    $host = strtolower(trim((string) ($partes['host'] ?? ''), '[]'));
    if (!in_array($esquema, ['http', 'https'], true) || $host === ''
        || isset($partes['user']) || isset($partes['pass'])) {
        throw new ContenidoRemotoException('La URL debe ser pública y usar http o https, sin credenciales incorporadas.');
    }
    if (in_array($host, ['localhost', 'localhost.localdomain'], true)
        || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')
        || str_ends_with($host, '.internal') || str_ends_with($host, '.home.arpa')) {
        throw new ContenidoRemotoException('La URL no apunta a un sitio público permitido.');
    }

    $puerto = isset($partes['port']) ? (int) $partes['port'] : ($esquema === 'https' ? 443 : 80);
    if (($esquema === 'https' && $puerto !== 443) || ($esquema === 'http' && $puerto !== 80)) {
        throw new ContenidoRemotoException('La extracción admite únicamente los puertos web estándar.');
    }

    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $ips[] = $host;
    } else {
        $registros = @dns_get_record($host, DNS_A | DNS_AAAA);
        foreach (is_array($registros) ? $registros : [] as $registro) {
            $ip = (string) ($registro['ip'] ?? $registro['ipv6'] ?? '');
            if ($ip !== '') $ips[] = $ip;
        }
        if ($ips === []) {
            foreach (@gethostbynamel($host) ?: [] as $ip) $ips[] = (string) $ip;
        }
    }
    $ips = array_values(array_unique($ips));
    if ($ips === [] || count(array_filter($ips, 'ip_publica_contenido_remoto')) !== count($ips)) {
        throw new ContenidoRemotoException('La URL no apunta a un sitio público permitido.');
    }
    usort($ips, static fn(string $a, string $b): int => (int) str_contains($a, ':') <=> (int) str_contains($b, ':'));

    return ['url' => $url, 'host' => $host, 'port' => $puerto, 'ip' => $ips[0]];
}

function descargar_html_publico_contenido_remoto(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new ContenidoRemotoException('El servidor no tiene disponible la conexión para extraer páginas.', 503);
    }

    $maximoBytes = 2 * 1024 * 1024;
    for ($redireccion = 0; $redireccion <= 3; $redireccion++) {
        $destino = validar_url_publica_contenido_remoto($url);
        $cuerpo = '';
        $superoLimite = false;
        $ipResolve = str_contains($destino['ip'], ':') ? '[' . $destino['ip'] . ']' : $destino['ip'];
        $curl = curl_init($destino['url']);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'CardonaHoy-Extractor/1.0 (+https://cardonahoy.com/)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,text/plain;q=0.8',
                'Accept-Language: es,en;q=0.7',
            ],
            CURLOPT_RESOLVE => [
                $destino['host'] . ':' . $destino['port'] . ':' . $ipResolve,
            ],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $datos) use (&$cuerpo, &$superoLimite, $maximoBytes): int {
                if (strlen($cuerpo) + strlen($datos) > $maximoBytes) {
                    $superoLimite = true;
                    return 0;
                }
                $cuerpo .= $datos;
                return strlen($datos);
            },
        ]);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        $ok = curl_exec($curl);
        $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $tipo = strtolower(trim(explode(';', (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
        $siguiente = (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        $error = curl_error($curl);
        curl_close($curl);

        if ($superoLimite) {
            throw new ContenidoRemotoException('La página es demasiado grande para extraerla. Probá con Pegar contenido.');
        }
        if ($ok === false || $error !== '') {
            throw new ContenidoRemotoException('No pudimos conectarnos con ese sitio. Probá nuevamente o usá Pegar contenido.', 502);
        }
        if (in_array($estado, [301, 302, 303, 307, 308], true) && $siguiente !== '') {
            if ($redireccion === 3) {
                throw new ContenidoRemotoException('La página redirige demasiadas veces. Probá con Pegar contenido.');
            }
            $url = $siguiente;
            continue;
        }
        if ($estado < 200 || $estado >= 300) {
            throw new ContenidoRemotoException('El sitio no permitió obtener la noticia. Probá con Pegar contenido.', 502);
        }
        if ($tipo !== '' && !in_array($tipo, ['text/html', 'application/xhtml+xml', 'text/plain'], true)) {
            throw new ContenidoRemotoException('La URL no corresponde a una página de texto compatible.');
        }
        return $cuerpo;
    }

    throw new ContenidoRemotoException('No pudimos completar la extracción.', 502);
}

function texto_limpio_nodo_contenido_remoto(DOMNode $nodo): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($nodo->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function extraer_contenido_principal_html(string $html): string
{
    if (trim($html) === '') {
        throw new ContenidoRemotoException('La página no devolvió contenido utilizable. Probá con Pegar contenido.');
    }

    $estadoLibxml = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $cargado = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($estadoLibxml);
    if (!$cargado) {
        throw new ContenidoRemotoException('No pudimos interpretar el contenido de esa página. Probá con Pegar contenido.');
    }

    $xpath = new DOMXPath($doc);
    $ruido = [];
    foreach ($xpath->query('//script|//style|//noscript|//nav|//footer|//form|//button|//svg|//iframe|//aside') ?: [] as $nodo) {
        $ruido[] = $nodo;
    }
    foreach ($ruido as $nodo) $nodo->parentNode?->removeChild($nodo);

    $candidatos = [];
    foreach ($xpath->query(
        '//article|//main|//*[@itemprop="articleBody"]'
        . '|//*[contains(concat(" ", normalize-space(@class), " "), " article-body ")]'
        . '|//*[contains(concat(" ", normalize-space(@class), " "), " entry-content ")]'
        . '|//*[contains(concat(" ", normalize-space(@class), " "), " post-content ")]'
    ) ?: [] as $nodo) {
        $texto = texto_limpio_nodo_contenido_remoto($nodo);
        if ($texto !== '') $candidatos[] = ['nodo' => $nodo, 'largo' => mb_strlen($texto, 'UTF-8')];
    }
    usort($candidatos, static fn(array $a, array $b): int => $b['largo'] <=> $a['largo']);
    $principal = $candidatos[0]['nodo'] ?? $doc->getElementsByTagName('body')->item(0);
    if (!$principal) {
        throw new ContenidoRemotoException('No encontramos texto principal en esa página. Probá con Pegar contenido.');
    }

    $fragmentos = [];
    $vistos = [];
    foreach ($xpath->query('.//h1|.//h2|.//h3|.//p|.//li|.//blockquote', $principal) ?: [] as $nodo) {
        $texto = texto_limpio_nodo_contenido_remoto($nodo);
        if (mb_strlen($texto, 'UTF-8') < 20) continue;
        $huella = hash('sha256', mb_strtolower($texto, 'UTF-8'));
        if (isset($vistos[$huella])) continue;
        $vistos[$huella] = true;
        $fragmentos[] = $texto;
    }
    if ($fragmentos === []) {
        $texto = texto_limpio_nodo_contenido_remoto($principal);
        if ($texto !== '') $fragmentos[] = $texto;
    }

    $tituloNodo = $doc->getElementsByTagName('title')->item(0);
    $titulo = $tituloNodo ? texto_limpio_nodo_contenido_remoto($tituloNodo) : '';
    if ($titulo !== '' && mb_strlen($titulo, 'UTF-8') <= 300 && ($fragmentos[0] ?? '') !== $titulo) {
        array_unshift($fragmentos, 'Título de la fuente: ' . $titulo);
    }

    $resultado = '';
    foreach ($fragmentos as $fragmento) {
        $pieza = '<p>' . htmlspecialchars($fragmento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        if (strlen($resultado) + strlen($pieza) > 60_000) break;
        $resultado .= $pieza;
    }
    $textoFinal = trim(html_entity_decode(strip_tags($resultado), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (mb_strlen($textoFinal, 'UTF-8') < 80) {
        throw new ContenidoRemotoException('No pudimos extraer suficiente texto de esa página. Probá con Pegar contenido.');
    }
    return $resultado;
}

function extraer_contenido_url(string $url): string
{
    return extraer_contenido_principal_html(descargar_html_publico_contenido_remoto($url));
}
