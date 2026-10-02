<?php
/**
 * Mediamora Toolkit, module: jQuery-wachtrem
 * Overgenomen uit de testcode in functions.php (Falcon-i, sanbao.be).
 * Wordt alleen geladen als de module aanstaat.
 *
 * Met jQuery uitgesteld (LiteSpeed defer) voert jQuery uit als readyState al
 * 'interactive' is en plant jQuery.ready met een setTimeout. Wacht de browser
 * daarna nog op het grote bestand van Elementor Pro (eerste bezoek, incognito,
 * mobiel), dan start Elementor tussendoor en vuurt elementor/frontend/init af
 * voordat Pro luistert. Pro start dan nooit (elementorProFrontend.modules
 * blijft leeg): mobiel menu, sticky headers en Pro-formulieren werken die
 * paginaweergave niet.
 *
 * Dit regeltje direct achter jQuery houdt jQuery.ready vast tot
 * DOMContentLoaded. Alle uitgestelde scripts, ook die van Pro, zijn dan
 * uitgevoerd. Bewezen op sanbao.be en Falcon-i met een kunstmatig vertraagd
 * Pro-bestand: zonder 0 modules, met 32 en het menu opent.
 *
 * Bewust zonder data-no-defer: het moet net als jQuery uitgesteld lopen,
 * direct erachter. Is jQuery niet uitgesteld, dan doet het niets schadelijks.
 *
 * jQuery.holdReady is verouderd sinds jQuery 3.2. Bij een overstap naar
 * jQuery 4 opnieuw bekijken; ontbreekt holdReady dan, dan doet dit niets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prioriteit 20 en niet 1 zoals de testversie: functions.php laadt na de
 * plugins, dus de testversie op prioriteit 1 is dan al toegevoegd en wordt
 * hieronder herkend. Zo komt het regeltje er nooit twee keer.
 */
function mm_jquery_wacht_toevoegen() {

	$scripts = wp_scripts();

	if ( ! isset( $scripts->registered['jquery-core'] ) ) {
		return;
	}

	foreach ( (array) $scripts->get_data( 'jquery-core', 'after' ) as $regel ) {
		if ( is_string( $regel ) && false !== strpos( $regel, 'holdReady' ) ) {
			return;
		}
	}

	wp_add_inline_script( 'jquery-core', "if(window.jQuery&&jQuery.holdReady&&document.readyState!=='complete'){jQuery.holdReady(true);document.addEventListener('DOMContentLoaded',function(){jQuery.holdReady(false);});}", 'after' );
}

add_action( 'wp_enqueue_scripts', 'mm_jquery_wacht_toevoegen', 20 );
