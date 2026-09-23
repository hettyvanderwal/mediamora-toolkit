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

    $pad = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $pad = strtok($pad, '?');
    $pad = mb_substr($pad, 0, 190);

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

    if (isset($log[$bot][$pad])) {
        $log[$bot][$pad]++;
    } elseif (count($log[$bot]) < MM_AIBOTS_MAX_PADEN) {
        $log[$bot][$pad] = 1;
    } else {
        // Limiet bereikt: alleen het totaal blijven ophogen.
        $log[$bot]['(overig)'] = (int) ($log[$bot]['(overig)'] ?? 0) + 1;
    }

    arsort($log[$bot]);

    update_option($key, $log, false);
}

/**
 * Ruimt logs op die ouder zijn dan zes maanden.
 */
add_action('mm_aibots_opschonen', 'mm_aibots_opschonen');

function mm_aibots_opschonen() {
    global $wpdb;
    $grens = 'mm_aibots_' . gmdate('Y-m', strtotime('-6 months'));
    $rijen = $wpdb->get_col(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mm\\_aibots\\_%'"
    );
    foreach ((array) $rijen as $naam) {
        if (strcmp($naam, $grens) < 0) {
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
 * Moet op admin_init, en niet op een van de load-hooks van het scherm zelf:
 * die bestaan niet meer nu de pagina niet langer onder tools.php hangt.
 * WordPress zou tools.php?page=mm-aibots afdoen met "Je hebt geen toegang
 * tot deze pagina", en dat gebeurt pas na admin_init.
 */
add_action('admin_init', 'mm_aibots_stuur_oude_url_door');

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
        if (preg_match('/^\d{4}-\d{2}$/', $maand)) {
            $maanden[] = $maand;
        }
    }

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

    if (!$maanden) {
        echo '<p>Er is nog niets gelogd. Zodra een bekende AI-crawler de site bezoekt, verschijnt hier een overzicht.</p></div>';

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
