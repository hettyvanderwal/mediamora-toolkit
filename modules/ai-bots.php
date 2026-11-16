<?php
/**
 * Mediamora Toolkit, module: AI-bots
 * Overgenomen uit mediamora-ai-bots.php 1.1. Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_AIBOTS_MAX_PADEN = 400;

/**
 * User agents die er voor AI-zichtbaarheid toe doen.
 * Volgorde is van specifiek naar algemeen, de eerste treffer wint.
 */
function mm_aibots_lijst() {
    return [
        'OAI-SearchBot',
        'ChatGPT-User',
        'GPTBot',
        'Claude-SearchBot',
        'Claude-User',
        'ClaudeBot',
        'Perplexity-User',
        'PerplexityBot',
        'Google-Extended',
        'Applebot-Extended',
        'Applebot',
        'meta-externalagent',
        'DuckAssistBot',
        'MistralAI-User',
        'Amazonbot',
        'Bytespider',
        'CCBot',
        'YouBot',
        'cohere-ai',
        'Bingbot',
        'Googlebot',
    ];
}

/**
 * Deze twee zijn geen AI-crawler. Ze lopen mee als ijkpunt: zie je hier wel
 * bezoek en bij de AI-bots niets, dan is dat een aanwijzing voor een blokkade.
 */
function mm_aibots_referentie() {
    return ['Bingbot', 'Googlebot'];
}

/**
 * De crawlers die met "AI-crawlers buiten de cache houden" de cache
 * overslaan: de hele lijst zonder de ijkpunten.
 */
function mm_aibots_cache_bots() {
    return array_values(array_diff(mm_aibots_lijst(), mm_aibots_referentie()));
}

/**
 * Statische bestanden zijn ruis: ze zeggen niets over zichtbaarheid en
 * vullen wel de padenlimiet. CSS, afbeeldingen, fonts en de wp-mappen eruit.
 */
function mm_aibots_is_bestand($pad) {
    $ext = strtolower((string) pathinfo($pad, PATHINFO_EXTENSION));

    // Documenten zijn wel inhoud: een AI die een pdf of een sitemap ophaalt telt mee.
    if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'csv', 'xml'], true)) {
        return false;
    }

    foreach (['/wp-content/', '/wp-includes/', '/wp-admin/'] as $map) {
        if (stripos($pad, $map) !== false) {
            return true;
        }
    }

    $negeren = [
        'css', 'js', 'map', 'json', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif',
        'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp4', 'webm', 'mp3', 'zip',
    ];

    return $ext !== '' && in_array($ext, $negeren, true);
}

/**
 * Het pad uit REQUEST_URI zoals het in het log komt: zonder querystring,
 * zonder stuurtekens en hooguit 190 tekens. Niet gedecodeerd, zodat de
 * sleutels gelijk blijven aan die in bestaande logs.
 *
 * Het voorvoegsel is nodig omdat wp_parse_url() een pad als //voorbeeld.nl/x
 * anders als host leest.
 */
function mm_aibots_pad($uri) {
    // Stuurtekens eerst weg: parse_url() maakt er anders een _ van.
    $uri = preg_replace('/[\x00-\x1F\x7F]/', '', wp_unslash((string) $uri));
    $pad = (string) wp_parse_url('http://x' . $uri, PHP_URL_PATH);

    if ($pad === '' || $pad[0] !== '/') {
        $pad = '/' . $pad;
    }

    return mb_substr($pad, 0, 190);
}

add_action('init', 'mm_aibots_log', 1);

function mm_aibots_log() {
    if (is_admin() || wp_doing_cron() || wp_doing_ajax()) {
        return;
    }
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }

    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    if ($ua === '') {
        return;
    }

    $bot = '';
    foreach (mm_aibots_lijst() as $naam) {
        if (stripos($ua, $naam) !== false) {
            $bot = $naam;
            break;
        }
    }
    if ($bot === '') {
        return;
    }

    $pad = mm_aibots_pad(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');

    if (mm_aibots_is_bestand($pad)) {
        return;
    }

    $key = 'mm_aibots_' . gmdate('Y-m');
    $log = get_option($key, []);
    if (!is_array($log)) {
        $log = [];
    }

    if (!isset($log[$bot])) {
        $log[$bot] = [];
    }

    // Alleen ophogen. Sorteren gebeurt pas op het AI-bots-scherm, zodat een
    // bezoek niet meer kost dan nodig.
    if (isset($log[$bot][$pad])) {
        $log[$bot][$pad]++;
    } elseif (count($log[$bot]) < MM_AIBOTS_MAX_PADEN) {
        $log[$bot][$pad] = 1;
    } else {
        // Limiet bereikt: alleen het totaal blijven ophogen.
        $log[$bot]['(overig)'] = (int) ($log[$bot]['(overig)'] ?? 0) + 1;
    }

    update_option($key, $log, false);
}

/**
 * Ruimt logs op die ouder zijn dan zes maanden.
 */
add_action('mm_aibots_opschonen', 'mm_aibots_opschonen');

function mm_aibots_opschonen() {
    global $wpdb;
    $grens = gmdate('Y-m', strtotime('-6 months'));
    // Zowel de crawlerlogs (mm_aibots_JJJJ-MM) als de bezoekerslogs
    // (mm_aibots_bezoek_JJJJ-MM): de maand aan het eind telt.
    $rijen = $wpdb->get_col(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mm\\_aibots\\_%'"
    );
    foreach ((array) $rijen as $naam) {
        if (preg_match('/(\d{4}-\d{2})$/', $naam, $m) && strcmp($m[1], $grens) < 0) {
            delete_option($naam);
        }
    }
}

add_action('init', function () {
    if (!wp_next_scheduled('mm_aibots_opschonen')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mm_aibots_opschonen');
    }
}, 20);

/* -------------------------------------------------------------------------
 * Overzicht in de beheeromgeving
 * ---------------------------------------------------------------------- */

add_action('admin_menu', function () {
    add_options_page(
        'AI-bots',
        'AI-bots',
        'manage_options',
        'mm-aibots',
        'mm_aibots_pagina'
    );
});

/**
 * Het scherm stond tot en met 1.1.0 onder Gereedschap. Bladwijzers en
 * genoteerde links naar de oude plek blijven werken.
 *
 * Hangt aan admin_page_access_denied, de action die WordPress afvuurt vlak
 * voordat het "Je hebt geen toestemming om deze pagina te bekijken" toont.
 * Dat is precies wat er gebeurt bij tools.php?page=mm-aibots nu die pagina
 * daar niet meer geregistreerd staat, dus dit is het laatste moment waarop
 * we er nog tussen kunnen komen.
 *
 * Niet op admin_init: die toegangscheck staat in wp-admin/includes/menu.php,
 * en dat bestand wordt vanuit admin.php ingeladen voordat admin_init wordt
 * afgevuurd. Op admin_init zijn we dus al te laat.
 *
 * Deze action gaat af bij elke geweigerde beheerpagina, vandaar de twee
 * controles hieronder op pagenow en page.
 */
add_action('admin_page_access_denied', 'mm_aibots_stuur_oude_url_door');

function mm_aibots_stuur_oude_url_door() {
    global $pagenow;

    if ($pagenow !== 'tools.php') {
        return;
    }

    if (!isset($_GET['page']) || $_GET['page'] !== 'mm-aibots') {
        return;
    }

    $doel = admin_url('options-general.php?page=mm-aibots');

    // De gekozen maand meenemen, anders komt een bladwijzer naar een
    // specifieke maand alsnog op de huidige maand uit.
    if (isset($_GET['maand'])) {
        $doel = add_query_arg('maand', sanitize_text_field(wp_unslash($_GET['maand'])), $doel);
    }

    wp_safe_redirect($doel);
    exit;
}

/**
 * Alle maanden waarvoor een log bestaat, nieuwste eerst.
 */
function mm_aibots_maanden() {
    global $wpdb;

    $rijen = $wpdb->get_col(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mm\\_aibots\\_%' ORDER BY option_name DESC"
    );

    $maanden = [];
    foreach ((array) $rijen as $naam) {
        $maand = substr($naam, strlen('mm_aibots_'));
        if (strpos($maand, 'bezoek_') === 0) {
            $maand = substr($maand, strlen('bezoek_'));
        }
        if (preg_match('/^\d{4}-\d{2}$/', $maand)) {
            $maanden[] = $maand;
        }
    }

    $maanden = array_values(array_unique($maanden));
    rsort($maanden);

    return $maanden;
}

function mm_aibots_maandnaam($maand) {
    $tijd = strtotime($maand . '-01');

    return $tijd ? date_i18n('F Y', $tijd) : $maand;
}

function mm_aibots_pagina() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $maanden = mm_aibots_maanden();

    echo '<div class="wrap"><h1>AI-bots</h1>';

    mm_aibots_cache_formulier();

    if (!$maanden) {
        echo '<p>Er is nog niets gelogd. Zodra een bekende AI-crawler de site bezoekt, of een bezoeker via een AI-antwoord binnenkomt, verschijnt hier een overzicht.</p></div>';

        return;
    }

    $huidig = isset($_GET['maand']) ? sanitize_text_field(wp_unslash($_GET['maand'])) : $maanden[0];
    if (!in_array($huidig, $maanden, true)) {
        $huidig = $maanden[0];
    }

    $log = get_option('mm_aibots_' . $huidig, []);
    if (!is_array($log)) {
        $log = [];
    }

    if (count($maanden) > 1) {
        echo '<form method="get" style="margin:1em 0;">';
        echo '<input type="hidden" name="page" value="mm-aibots" />';
        echo '<label for="mm-aibots-maand">Maand: </label>';
        echo '<select name="maand" id="mm-aibots-maand" onchange="this.form.submit()">';
        foreach ($maanden as $maand) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($maand),
                selected($maand, $huidig, false),
                esc_html(mm_aibots_maandnaam($maand))
            );
        }
        echo '</select></form>';
    } else {
        printf('<p><strong>%s</strong></p>', esc_html(mm_aibots_maandnaam($huidig)));
    }

    $referentie = mm_aibots_referentie();

    $totalen = [];
    foreach ($log as $bot => $paden) {
        $totalen[$bot] = array_sum((array) $paden);
    }
    arsort($totalen);

    $ai = array_diff_key($totalen, array_flip($referentie));
    $ijk = array_intersect_key($totalen, array_flip($referentie));

    mm_aibots_bezoek_sectie($huidig);

    printf(
        '<p>%s bezoeken van AI-crawlers, verdeeld over %s bots.</p>',
        esc_html((string) array_sum($ai)),
        esc_html((string) count($ai))
    );

    mm_aibots_tabel('AI-crawlers', $ai, $log);

    if ($ijk) {
        mm_aibots_tabel('Zoekmachines (ter vergelijking)', $ijk, $log);
    }

    $gemist = array_diff(mm_aibots_lijst(), array_keys($log), $referentie);
    if ($gemist) {
        printf(
            '<p><em>Deze maand niet langsgeweest: %s.</em></p>',
            esc_html(implode(', ', $gemist))
        );
    }

    echo '</div>';
}

/* -------------------------------------------------------------------------
 * Instelling: AI-crawlers buiten de cache houden
 *
 * Het blok in .htaccess zelf regelt de toolkit, zie "AI-bots:
 * LiteSpeed-cache overslaan" in mediamora-toolkit.php.
 * ---------------------------------------------------------------------- */

function mm_aibots_cache_formulier() {
    $litespeed = mm_toolkit_litespeed_server() === true;
    $aan = (bool) get_option(MM_TOOLKIT_AIBOTS_CACHE_OPTIE, false);

    if (!empty($_GET['cache-opgeslagen'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Opgeslagen.</p></div>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:820px;margin:1em 0 1.5em;">';
    echo '<input type="hidden" name="action" value="mm_aibots_cache" />';
    wp_nonce_field('mm_aibots_cache');

    printf(
        '<label><input type="checkbox" name="mm_aibots_cache" value="1"%s%s /> <strong>AI-crawlers buiten de cache houden</strong></label>',
        checked($aan, true, false),
        disabled(!$litespeed, true, false)
    );

    if (!$litespeed) {
        echo ' <span style="color:#646970;">Alleen op LiteSpeed.</span>';
    }

    echo '<p class="description">LiteSpeed geeft gecachete pagina\'s zonder dat WordPress draait, en dan wordt een bezoek niet geteld. '
        . 'Met dit vinkje krijgen AI-crawlers altijd een verse pagina, zodat elk bezoek meetelt. '
        . 'Dat geeft meer serverbelasting, vooral bij drukke crawlers als GPTBot en Bytespider. '
        . 'Googlebot en Bingbot blijven gecachet; hun aantallen blijven daardoor te laag en zijn dan niet meer één op één te vergelijken met die van de AI-crawlers.</p>';

    if ($aan && get_transient('mm_toolkit_aibots_htaccess_fout')) {
        echo '<p style="color:#b32d2e;">Kon .htaccess niet bijwerken: AI-crawlers krijgen nog gecachete pagina\'s.</p>';
    }

    if ($litespeed) {
        submit_button('Opslaan', 'secondary', 'submit', false);
    }

    echo '</form>';
}

add_action('admin_post_mm_aibots_cache', 'mm_aibots_cache_opslaan');

function mm_aibots_cache_opslaan() {
    if (!current_user_can('manage_options')) {
        wp_die('Geen toegang.');
    }
    check_admin_referer('mm_aibots_cache');

    update_option(MM_TOOLKIT_AIBOTS_CACHE_OPTIE, !empty($_POST['mm_aibots_cache']), false);

    // Een nieuwe poging, ook als het eerder misging.
    delete_transient('mm_toolkit_aibots_htaccess_fout');
    mm_toolkit_aibots_cache_bijwerken(mm_toolkit_aibots_cache_gewenst());

    wp_safe_redirect(add_query_arg('cache-opgeslagen', '1', admin_url('options-general.php?page=mm-aibots')));
    exit;
}

function mm_aibots_tabel($titel, $totalen, $log) {
    printf('<h2>%s</h2>', esc_html($titel));

    if (!$totalen) {
        echo '<p>Geen bezoeken deze maand.</p>';

        return;
    }

    foreach ($totalen as $bot => $totaal) {
        $paden = isset($log[$bot]) ? (array) $log[$bot] : [];
        arsort($paden);

        printf(
            '<h3 style="margin-bottom:.3em;">%s <span style="font-weight:400;color:#646970;">(%s %s, %s %s)</span></h3>',
            esc_html($bot),
            esc_html((string) $totaal),
            esc_html($totaal === 1 ? 'bezoek' : 'bezoeken'),
            esc_html((string) count($paden)),
            esc_html(count($paden) === 1 ? 'pagina' : 'pagina\'s')
        );

        echo '<table class="widefat striped" style="max-width:820px;margin-bottom:1.5em;"><thead><tr><th>Pagina</th><th style="width:100px;">Bezoeken</th></tr></thead><tbody>';

        foreach ($paden as $pad => $aantal) {
            echo '<tr><td>';
            if ($pad === '(overig)') {
                echo '<em>overige pagina\'s</em>';
            } else {
                printf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url(home_url($pad)),
                    esc_html($pad)
                );
            }
            printf('</td><td>%s</td></tr>', esc_html((string) $aantal));
        }

        echo '</tbody></table>';
    }
}

/* -------------------------------------------------------------------------
 * Bezoekers vanuit AI-antwoorden
 *
 * De crawlers hierboven laten zien dat een AI de site leest. Dit deel telt
 * de mensen die daarna echt doorklikken: bezoekers met een AI-dienst als
 * verwijzer (chatgpt.com, perplexity.ai, ...) of met zo'n dienst in
 * utm_source (ChatGPT zet utm_source=chatgpt.com achter zijn links).
 *
 * Tellen gebeurt in de browser: een echte bezoeker krijgt op LiteSpeed
 * meestal een gecachete pagina zonder dat PHP draait. Een klein script in
 * de pagina kijkt naar de verwijzer en de URL, en meldt alleen een bezoek
 * als het om een AI-dienst gaat. Dat gaat via admin-ajax.php, omdat ASE
 * de REST API voor bezoekers vaak dichtzet.
 *
 * Geen cookies, geen sessionStorage, geen IP-adres: alleen een teller per
 * maand, per bron en per pagina. Een herlaadactie telt daardoor opnieuw.
 * Google AI Overviews en AI Mode komen binnen als google.com en zijn zo
 * niet van gewoon zoekverkeer te onderscheiden; die staan in het rapport
 * Generatieve AI van Search Console.
 * ---------------------------------------------------------------------- */

/**
 * Bekende AI-diensten. Per dienst de verwijzers (domein, subdomeinen tellen
 * mee) en de woorden waaraan een utm_source herkend wordt. Een utm_source
 * die gelijk is aan een van de domeinen telt ook.
 */
function mm_aibots_bronnen() {
    return [
        'ChatGPT'    => ['hosts' => ['chatgpt.com', 'chat.openai.com'], 'utm' => ['chatgpt', 'openai']],
        'Perplexity' => ['hosts' => ['perplexity.ai'], 'utm' => ['perplexity']],
        'Gemini'     => ['hosts' => ['gemini.google.com', 'bard.google.com'], 'utm' => ['gemini']],
        'Copilot'    => ['hosts' => ['copilot.microsoft.com', 'copilot.cloud.microsoft', 'm365.cloud.microsoft'], 'utm' => ['copilot']],
        'Claude'     => ['hosts' => ['claude.ai'], 'utm' => ['claude.ai', 'anthropic']],
        'Mistral'    => ['hosts' => ['chat.mistral.ai'], 'utm' => ['mistral']],
        'DeepSeek'   => ['hosts' => ['chat.deepseek.com'], 'utm' => ['deepseek']],
        'Meta AI'    => ['hosts' => ['meta.ai'], 'utm' => ['meta.ai']],
        'Grok'       => ['hosts' => ['grok.com'], 'utm' => ['grok']],
        'You.com'    => ['hosts' => ['you.com'], 'utm' => ['you.com']],
    ];
}

/**
 * Zet een host of utm-waarde om naar kleine letters, zonder www. en
 * zonder punt aan het eind.
 */
function mm_aibots_bron_normaal($waarde) {
    $waarde = strtolower(trim((string) $waarde));
    $waarde = preg_replace('/^www\./', '', $waarde);

    return rtrim(mb_substr($waarde, 0, 100), '.');
}

/**
 * Welke AI-dienst hoort bij deze verwijzer of utm_source? Lege tekst als
 * het geen bekende dienst is. De verwijzer gaat voor.
 *
 * @param string $host Host van de verwijzer, zonder pad.
 * @param string $utm  Waarde van utm_source.
 * @return string
 */
function mm_aibots_bron($host, $utm) {
    $host = mm_aibots_bron_normaal($host);
    $utm  = mm_aibots_bron_normaal($utm);

    if ($host !== '') {
        foreach (mm_aibots_bronnen() as $naam => $bron) {
            foreach ($bron['hosts'] as $domein) {
                if ($host === $domein || substr($host, -strlen('.' . $domein)) === '.' . $domein) {
                    return $naam;
                }
            }
        }
    }

    if ($utm !== '') {
        foreach (mm_aibots_bronnen() as $naam => $bron) {
            if (in_array($utm, $bron['hosts'], true)) {
                return $naam;
            }
            foreach ($bron['utm'] as $woord) {
                if (strpos($utm, $woord) !== false) {
                    return $naam;
                }
            }
        }
    }

    return '';
}

/**
 * Hoogt de teller op voor één bezoek. Zelfde opbouw als het crawlerlog:
 * [bron => [pad => aantal]], met dezelfde padenlimiet.
 */
function mm_aibots_bezoek_tel($bron, $pad) {
    $key = 'mm_aibots_bezoek_' . gmdate('Y-m');
    $log = get_option($key, []);
    if (!is_array($log)) {
        $log = [];
    }

    if (!isset($log[$bron])) {
        $log[$bron] = [];
    }

    if (isset($log[$bron][$pad])) {
        $log[$bron][$pad]++;
    } elseif (count($log[$bron]) < MM_AIBOTS_MAX_PADEN) {
        $log[$bron][$pad] = 1;
    } else {
        $log[$bron]['(overig)'] = (int) ($log[$bron]['(overig)'] ?? 0) + 1;
    }

    update_option($key, $log, false);
}

/**
 * Beoordeelt een melding uit de browser en telt hem als hij klopt.
 *
 * Een melding kan iedereen sturen, dus de server beslist: alleen een
 * bekende bron, een pagina op deze site en geen crawler als user agent.
 * Komt de melding met een Origin van een ander domein, dan telt hij niet.
 *
 * @param array  $data   De POST-velden h (host verwijzer), u (utm_source), p (pad).
 * @param string $ua     User agent van de melding.
 * @param string $origin Origin-header van de melding, of leeg.
 * @return string De getelde bron, of leeg als er niets geteld is.
 */
function mm_aibots_bezoek_verwerk($data, $ua, $origin) {
    $data = is_array($data) ? $data : [];

    if ($origin !== '') {
        $eigen = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $van   = strtolower((string) wp_parse_url($origin, PHP_URL_HOST));
        if ($van === '' || preg_replace('/^www\./', '', $van) !== preg_replace('/^www\./', '', $eigen)) {
            return '';
        }
    }

    foreach (mm_aibots_lijst() as $naam) {
        if (stripos((string) $ua, $naam) !== false) {
            return '';
        }
    }

    $host = isset($data['h']) ? sanitize_text_field(wp_unslash((string) $data['h'])) : '';
    $utm  = isset($data['u']) ? sanitize_text_field(wp_unslash((string) $data['u'])) : '';
    $bron = mm_aibots_bron($host, $utm);
    if ($bron === '') {
        return '';
    }

    $pad = mm_aibots_pad(isset($data['p']) ? (string) $data['p'] : '/');
    if (mm_aibots_is_bestand($pad)) {
        return '';
    }

    mm_aibots_bezoek_tel($bron, $pad);

    return $bron;
}

add_action('wp_ajax_nopriv_mm_aibezoek', 'mm_aibots_bezoek_ontvang');
add_action('wp_ajax_mm_aibezoek', 'mm_aibots_bezoek_ontvang');

function mm_aibots_bezoek_ontvang() {
    mm_aibots_bezoek_verwerk(
        $_POST,
        isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '',
        isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : ''
    );

    // Het script kijkt niet naar het antwoord.
    status_header(204);
    exit;
}

/**
 * Het script in de pagina. Doet niets tenzij verwijzer of utm_source bij
 * een bekende AI-dienst hoort; de lijst komt uit mm_aibots_bronnen(), zodat
 * er maar één plek is om een dienst toe te voegen.
 *
 * In de head en met data-no-optimize/data-no-defer, zodat LiteSpeed het
 * niet uitstelt tot de eerste muisbeweging: wie meteen weer weggaat, telt
 * dan ook.
 */
add_action('wp_head', 'mm_aibots_bezoek_script', 1);

function mm_aibots_bezoek_script() {
    if (is_admin() || is_user_logged_in() || is_feed() || is_robots()) {
        return;
    }

    $hosts = [];
    $utm   = [];
    foreach (mm_aibots_bronnen() as $bron) {
        $hosts = array_merge($hosts, $bron['hosts']);
        $utm   = array_merge($utm, $bron['utm']);
    }

    $instellingen = wp_json_encode([
        'a' => admin_url('admin-ajax.php'),
        'h' => array_values(array_unique($hosts)),
        'u' => array_values(array_unique($utm)),
    ]);

    $js = '(function(c){try{'
        . 'var h="",r=document.referrer,u="";'
        . 'if(r){h=new URL(r).hostname.toLowerCase().replace(/^www\./,"");}'
        . 'if(h===location.hostname.toLowerCase().replace(/^www\./,"")){h="";}'
        . 'u=(new URLSearchParams(location.search).get("utm_source")||"").toLowerCase();'
        . 'function t(w,s){if(!w)return false;for(var i=0;i<c.h.length;i++){var d=c.h[i];if(w===d||(!s&&w.slice(-d.length-1)==="."+d))return true;}'
        . 'if(s){for(var j=0;j<c.u.length;j++){if(w.indexOf(c.u[j])!==-1)return true;}}return false;}'
        . 'if(!t(h,false)&&!t(u,true))return;'
        . 'var d=new URLSearchParams();d.append("action","mm_aibezoek");d.append("h",h);d.append("u",u);d.append("p",location.pathname);'
        . 'if(navigator.sendBeacon){navigator.sendBeacon(c.a,d);}else{fetch(c.a,{method:"POST",body:d,keepalive:true,credentials:"omit"});}'
        . '}catch(e){}})(' . $instellingen . ');';

    wp_print_inline_script_tag($js, [
        'id'                => 'mm-aibezoek',
        'data-no-optimize'  => '1',
        'data-no-defer'     => '1',
        'data-cfasync'      => 'false',
    ]);
}

/**
 * Het blok bovenaan het AI-bots-scherm.
 */
function mm_aibots_bezoek_sectie($maand) {
    $log = get_option('mm_aibots_bezoek_' . $maand, []);
    if (!is_array($log)) {
        $log = [];
    }

    $totalen = [];
    foreach ($log as $bron => $paden) {
        $totalen[$bron] = array_sum((array) $paden);
    }
    arsort($totalen);

    printf(
        '<p>%s %s vanuit AI-antwoorden.</p>',
        esc_html((string) array_sum($totalen)),
        esc_html(array_sum($totalen) === 1 ? 'bezoeker' : 'bezoekers')
    );

    mm_aibots_tabel('Bezoekers vanuit AI-antwoorden', $totalen, $log);

    echo '<p class="description" style="max-width:820px;margin-bottom:2em;">'
        . 'Mensen die via een link in ChatGPT, Perplexity, Gemini, Copilot, Claude of een andere AI-dienst op de site komen, per landingspagina. '
        . 'Herkend aan de verwijzer of aan utm_source. Zonder cookies en zonder IP-adres; herladen telt opnieuw, ingelogde gebruikers tellen niet. '
        . 'Google AI Overviews en AI Mode zijn hier niet te zien: die komen binnen als gewoon Google-verkeer en staan in Search Console onder Generatieve AI.</p>';
}
