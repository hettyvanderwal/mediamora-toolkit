<?php
/**
 * Mediamora Toolkit, Formuliermonitor: de maandelijkse formuliertest.
 *
 * Hoort bij de module Formuliermonitor en laadt alleen met die module, dus
 * alleen op een onderhoudsklant. formuliermonitor.php laadt dit bestand.
 *
 * Eén keer per maand, op een vast moment per site in de eerste drie dagen,
 * gaan alle Elementor Pro-formulieren met een mailactie (email of email2)
 * door de normale verwerking van Elementor Pro: de validatie van Elementor,
 * de anti-spam en de verwerking van de velden. Daarna draaien alleen de
 * mailacties. Hun mail wordt helemaal opgebouwd en vlak voor het versturen
 * onderschept, zodat de klant en Mediamora per formulier niets krijgen.
 * Daarnaast gaat er per ronde één echte mail via wp_mail() naar
 * MM_FORMULIERTEST_ONTVANGER, om de mailroute zelf te testen.
 *
 * Alleen als er iets mislukt, komt er een melding naar MM_MONITOR_ONTVANGER,
 * via mail() van de Formuliermonitor. Bij een geslaagde ronde komt er niets.
 *
 * Nagekeken in Elementor Pro 4.3.3 (modules/forms):
 * - classes/ajax-handler.php, ajax_send_form(): zoekt het formulier op met
 *   Module::find_element_recursive() in het document van post_id, vervangt
 *   een globale widget (templateID) door het eerste element van dat
 *   template, maakt het widget met elements_manager->create_element_instance()
 *   en neemt get_settings_for_display() als instellingen. Dan new
 *   Form_Record( $_POST['form_fields'], $form ), validate(), process_fields(),
 *   de filters record/actions_before en submit_actions, per actie run(), de
 *   actie actions/after_run, de componenten activity_log en cf7db en de actie
 *   new_record. Dat stuk na process_fields() slaat de test over: daar haken
 *   de koppelingen, Inzendingen (de actie save-to-database registreert zijn
 *   after_run pas in run()) en de module Nieuwsbrief alleen bij vinkje in.
 *   De test roept alleen run() aan van email en email2.
 * - classes/form-record.php: validate() vuurt per veld
 *   elementor_pro/forms/validation/{type} en daarna
 *   elementor_pro/forms/validation, waar de anti-spam, de honeypot en
 *   reCAPTCHA v3 aan hangen. De meta page_url en page_title komen uit
 *   $_POST['referrer'] en $_POST['referer_title'].
 * - actions/email.php, run(): bouwt de mail op en verstuurt hem met
 *   wp_mail(), voor elk Bcc-adres nog een keer. Lukt wp_mail() niet, dan een
 *   foutmelding en een exception.
 *
 * Ajax_Handler zelf wordt gemaakt zonder constructor, want die hangt
 * ajax_send_form() aan de ajax-acties. De velden van Elementor verwachten
 * een echte Ajax_Handler (tel.php: Classes\Ajax_Handler $ajax_handler).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_FORMULIERTEST_HOOK  = 'mm_formuliertest_maandelijks';
const MM_FORMULIERTEST_OPTIE = 'mm_formuliertest_laatste';
const MM_FORMULIERTEST_MAAND = 'mm_formuliertest_maand';
const MM_FORMULIERTEST_SLOT  = 'mm_formuliertest_slot';
const MM_FORMULIERTEST_MAX   = 10;

/**
 * Het venster aan het begin van de maand, in seconden: 72 uur.
 */
const MM_FORMULIERTEST_VENSTER = 259200;

/**
 * De mailacties van Elementor Pro. Alleen die draaien in de test.
 */
const MM_FORMULIERTEST_ACTIES = array( 'email', 'email2' );

/**
 * De uitkomsten per formulier. Alleen geslaagd en niet te testen tellen
 * niet als mislukt.
 */
const MM_FORMULIERTEST_GESLAAGD       = 'geslaagd';
const MM_FORMULIERTEST_GEWEIGERD      = 'geweigerd';
const MM_FORMULIERTEST_VALIDATIEFOUT  = 'validatiefout';
const MM_FORMULIERTEST_MAIL_FOUT      = 'mail_niet_op_te_bouwen';
const MM_FORMULIERTEST_NIET_TE_TESTEN = 'niet_te_testen';

/**
 * De ingevulde waarden. Komen nergens in een log, de uitkomst of de sync.
 */
const MM_FORMULIERTEST_WAARDEN = array(
	'naam'     => 'Hetty',
	'email'    => 'hetty@mediamora.nl',
	'telefoon' => '0612345678',
	'postcode' => '9700 AA',
	'plaats'   => 'Groningen',
	'bericht'  => 'Automatische maandelijkse test van Mediamora, niet beantwoorden.',
	'tekst'    => 'test',
);

// Het adres voor de mailroute, MM_FORMULIERTEST_ONTVANGER, staat in
// wp-config.php. Zonder die constante wordt de mailroute niet getest.


/* -------------------------------------------------------------------------
 * Tijdens de test
 *
 * De Formuliermonitor en de anti-spam kijken hiernaar: zolang de test loopt,
 * is een mislukte of geslaagde mail geen storing of herstel, en komt er
 * niets in de logbestanden van de anti-spam.
 * ---------------------------------------------------------------------- */

/**
 * Loopt de formuliertest nu?
 *
 * @param bool|null $zet true of false om te zetten, null om te lezen.
 * @return bool
 */
function mm_formuliertest_bezig( $zet = null ) {
	static $bezig = false;
	if ( null !== $zet ) {
		$bezig = (bool) $zet;
	}
	return $bezig;
}

/**
 * Weigeringen van de anti-spam tijdens de test van één formulier. De
 * anti-spam roept dit aan in plaats van te loggen.
 *
 * @param string|null $reden Null om de lijst te lezen.
 * @param string      $veld
 * @return array<int, array{reden: string, veld: string}>
 */
function mm_formuliertest_weigering( $reden = null, $veld = '' ) {
	static $lijst = array();
	if ( null === $reden ) {
		$uit   = $lijst;
		$lijst = array();
		return $uit;
	}
	$lijst[] = array(
		'reden' => (string) $reden,
		'veld'  => (string) $veld,
	);
	return $lijst;
}


/* -------------------------------------------------------------------------
 * Het vaste moment
 * ---------------------------------------------------------------------- */

/**
 * Hoeveel seconden na het begin van de maand deze site aan de beurt is: vast
 * per domein, binnen de eerste 72 uur. Zo draaien 100+ sites niet tegelijk.
 *
 * @param string $domein Zonder www, zie mm_monitor_domein().
 * @return int
 */
function mm_formuliertest_verschuiving( $domein ) {
	return absint( crc32( strtolower( (string) $domein ) ) ) % MM_FORMULIERTEST_VENSTER;
}

/**
 * Het moment van deze site in een maand, als Unix-tijd. De maand begint om
 * middernacht in de tijdzone van de site.
 *
 * @param int    $jaar
 * @param int    $maand
 * @param string $domein
 * @return int
 */
function mm_formuliertest_moment( $jaar, $maand, $domein ) {
	$begin = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $jaar, $maand ), wp_timezone() );
	return $begin->getTimestamp() + mm_formuliertest_verschuiving( $domein );
}

/**
 * De maand waar $nu in valt, in de tijdzone van de site.
 *
 * @param int $nu
 * @return int[] Jaar en maand.
 */
function mm_formuliertest_maand_van( $nu ) {
	$datum = ( new DateTimeImmutable( '@' . (int) $nu ) )->setTimezone( wp_timezone() );
	return array( (int) $datum->format( 'Y' ), (int) $datum->format( 'n' ) );
}

/**
 * Het eerstvolgende moment na $nu.
 *
 * @param int    $nu
 * @param string $domein
 * @return int
 */
function mm_formuliertest_volgende( $nu, $domein ) {
	list( $jaar, $maand ) = mm_formuliertest_maand_van( $nu );
	$moment               = mm_formuliertest_moment( $jaar, $maand, $domein );
	if ( $moment > $nu ) {
		return $moment;
	}
	return 12 === $maand ? mm_formuliertest_moment( $jaar + 1, 1, $domein ) : mm_formuliertest_moment( $jaar, $maand + 1, $domein );
}

/**
 * De maand van het laatste moment dat al voorbij is, als "2026-11". Daar
 * hoort de ronde bij die dan had moeten draaien.
 *
 * @param int    $nu
 * @param string $domein
 * @return string
 */
function mm_formuliertest_huidige_ronde( $nu, $domein ) {
	list( $jaar, $maand ) = mm_formuliertest_maand_van( $nu );
	if ( mm_formuliertest_moment( $jaar, $maand, $domein ) > $nu ) {
		if ( 1 === $maand ) {
			$jaar--;
			$maand = 12;
		} else {
			$maand--;
		}
	}
	return sprintf( '%04d-%02d', $jaar, $maand );
}


/* -------------------------------------------------------------------------
 * Inplannen
 *
 * Eén eenmalig event op het volgende moment; na de ronde komt het volgende
 * erin. Het vangnet: is het moment van deze maand voorbij en de ronde van
 * deze maand niet gedaan, dan is het event kwijt of overgeslagen. Dan gaat
 * de ronde alsnog zo snel mogelijk, ook na de eerste drie dagen.
 *
 * Bij de eerste keer (na de update of het aanzetten) telt de ronde van deze
 * maand als gedaan. Anders draaien alle sites tegelijk zodra ze 1.34.0
 * krijgen, in plaats van op hun eigen moment.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mm_formuliertest_inplannen' );
add_action( MM_FORMULIERTEST_HOOK, 'mm_formuliertest_maandelijks' );

function mm_formuliertest_inplannen() {
	$nu     = time();
	$domein = mm_monitor_domein();
	$ronde  = mm_formuliertest_huidige_ronde( $nu, $domein );

	// Autoload: deze optie wordt bij elk verzoek gelezen.
	if ( false === get_option( MM_FORMULIERTEST_MAAND, false ) ) {
		add_option( MM_FORMULIERTEST_MAAND, $ronde, '', true );
	}

	$volgende = wp_next_scheduled( MM_FORMULIERTEST_HOOK );

	if ( get_option( MM_FORMULIERTEST_MAAND, '' ) !== $ronde ) {
		// Achterstallig. Een event dat al in het verleden staat, pakt WP-cron
		// bij het eerstvolgende verzoek zelf op.
		if ( ! $volgende || $volgende > $nu ) {
			wp_clear_scheduled_hook( MM_FORMULIERTEST_HOOK );
			wp_schedule_single_event( $nu, MM_FORMULIERTEST_HOOK );
		}
		return;
	}

	if ( ! $volgende ) {
		wp_schedule_single_event( mm_formuliertest_volgende( $nu, $domein ), MM_FORMULIERTEST_HOOK );
	}
}

/**
 * De maandelijkse ronde. Draait niet twee keer voor dezelfde maand, ook niet
 * als het vangnet en het event elkaar kruisen.
 */
function mm_formuliertest_maandelijks() {
	$nu     = time();
	$domein = mm_monitor_domein();
	$ronde  = mm_formuliertest_huidige_ronde( $nu, $domein );

	if ( get_option( MM_FORMULIERTEST_MAAND, '' ) !== $ronde && null !== mm_formuliertest_ronde( false ) ) {
		update_option( MM_FORMULIERTEST_MAAND, $ronde, true );
	}

	wp_clear_scheduled_hook( MM_FORMULIERTEST_HOOK );
	if ( get_option( MM_FORMULIERTEST_MAAND, '' ) === $ronde ) {
		wp_schedule_single_event( mm_formuliertest_volgende( $nu, $domein ), MM_FORMULIERTEST_HOOK );
	} else {
		// Er liep al een ronde (handmatig); over een kwartier opnieuw.
		wp_schedule_single_event( $nu + 15 * MINUTE_IN_SECONDS, MM_FORMULIERTEST_HOOK );
	}
}


/* -------------------------------------------------------------------------
 * De ronde
 * ---------------------------------------------------------------------- */

/**
 * Test alle formulieren en de mailroute, bewaart de uitkomst en stuurt
 * alleen bij een fout een melding. Handmatig of niet: het vaste moment
 * verschuift niet, dat doet alleen mm_formuliertest_maandelijks().
 *
 * @param bool $handmatig Gestart met de knop of WP-CLI.
 * @return array|null De uitkomst, of null als er al een ronde loopt.
 */
function mm_formuliertest_ronde( $handmatig ) {

	if ( ! mm_formuliertest_slot_nemen() ) {
		return null;
	}

	mm_formuliertest_bezig( true );

	try {
		$formulieren = array();
		foreach ( mm_formuliertest_formulieren() as $kandidaat ) {
			// Een fout bij het laden van één formulier breekt de ronde niet af.
			// Een echte inzending loopt daar ook op stuk, dus telt het als mislukt.
			try {
				$formulier = mm_formuliertest_test( $kandidaat );
			} catch ( \Throwable $e ) {
				$formulier = array(
					'pagina'    => $kandidaat['pagina'],
					'formulier' => $kandidaat['form_id'],
					'uitkomst'  => MM_FORMULIERTEST_MAIL_FOUT,
					'reden'     => 'fout bij het verwerken: ' . mm_formuliertest_tekst( $e->getMessage() ),
				);
			}
			if ( null !== $formulier ) {
				$formulieren[] = $formulier;
			}
		}
		$mailroute = mm_formuliertest_mailroute();
	} finally {
		mm_formuliertest_bezig( false );
		mm_formuliertest_weigering();
		mm_formuliertest_slot_vrijgeven();
	}

	$uitkomst = array(
		'tijd'        => time(),
		'handmatig'   => (bool) $handmatig,
		'totaal'      => mm_formuliertest_totaal( $formulieren, $mailroute ),
		'mailroute'   => $mailroute,
		'formulieren' => $formulieren,
		'gemeld'      => null,
	);

	if ( 'mislukt' === $uitkomst['totaal'] ) {
		$uitkomst['gemeld'] = mm_formuliertest_melding( $uitkomst );
	}

	update_option( MM_FORMULIERTEST_OPTIE, $uitkomst, false );

	return $uitkomst;
}

/**
 * Het totaal van een ronde: mislukt zodra één formulier of de mailroute
 * mislukt; niet getest als er niets getest is (geen formulier geslaagd en de
 * mailroute niet getest); anders in orde.
 *
 * @param array $formulieren
 * @param array $mailroute
 * @return string in_orde, mislukt of niet_getest.
 */
function mm_formuliertest_totaal( $formulieren, $mailroute ) {
	$getest = 'geslaagd' === $mailroute['uitkomst'];

	if ( 'mislukt' === $mailroute['uitkomst'] ) {
		return 'mislukt';
	}

	foreach ( $formulieren as $formulier ) {
		if ( mm_formuliertest_mislukt( $formulier['uitkomst'] ) ) {
			return 'mislukt';
		}
		$getest = $getest || MM_FORMULIERTEST_GESLAAGD === $formulier['uitkomst'];
	}

	return $getest ? 'in_orde' : 'niet_getest';
}

/**
 * Telt een uitkomst als mislukt? Alles behalve geslaagd en niet te testen.
 *
 * @param string $uitkomst
 * @return bool
 */
function mm_formuliertest_mislukt( $uitkomst ) {
	return ! in_array( $uitkomst, array( MM_FORMULIERTEST_GESLAAGD, MM_FORMULIERTEST_NIET_TE_TESTEN ), true );
}

/**
 * Slot tegen twee rondes tegelijk, zoals bij Verweesde meta opruimen. Een
 * slot van een afgebroken ronde vervalt na tien minuten.
 */
function mm_formuliertest_slot_nemen() {
	if ( add_option( MM_FORMULIERTEST_SLOT, time(), '', false ) ) {
		return true;
	}
	$sinds = (int) get_option( MM_FORMULIERTEST_SLOT, 0 );
	if ( $sinds && $sinds > time() - 10 * MINUTE_IN_SECONDS ) {
		return false;
	}
	update_option( MM_FORMULIERTEST_SLOT, time(), false );
	return true;
}

function mm_formuliertest_slot_vrijgeven() {
	delete_option( MM_FORMULIERTEST_SLOT );
}


/* -------------------------------------------------------------------------
 * De formulieren vinden
 *
 * Eerst de Theme Builder-templates en popups die in gebruik zijn, want een
 * formulier in de footer staat op elke pagina. Dan de gepubliceerde pagina's
 * en berichten. Een formulier via een globale widget telt één keer, net als
 * een formulier in een template dat via de widget Template op meerdere
 * pagina's staat. Hooguit MM_FORMULIERTEST_MAX.
 * ---------------------------------------------------------------------- */

/**
 * Draait Elementor Pro met de formulieren?
 *
 * @return bool
 */
function mm_formuliertest_elementor_pro() {
	return class_exists( '\ElementorPro\Plugin' ) && class_exists( '\ElementorPro\Modules\Forms\Module' ) && class_exists( '\ElementorPro\Modules\Forms\Classes\Form_Record' );
}

/**
 * @return array<int, array> De kandidaten, zie mm_formuliertest_zoek().
 */
function mm_formuliertest_formulieren() {

	if ( ! mm_formuliertest_elementor_pro() ) {
		return array();
	}

	$gevonden = array();
	$voorpag  = (int) get_option( 'page_on_front', 0 );

	foreach ( mm_formuliertest_templates_in_gebruik() as $template ) {
		$plek = array(
			'pagina'     => $template['titel'],
			'url'        => home_url( '/' ),
			'queried_id' => $voorpag ? $voorpag : $template['id'],
		);
		mm_formuliertest_zoek( mm_formuliertest_elementen( $template['id'] ), $template['id'], $plek, $gevonden );
		if ( count( $gevonden ) >= MM_FORMULIERTEST_MAX ) {
			break;
		}
	}

	if ( count( $gevonden ) < MM_FORMULIERTEST_MAX ) {
		foreach ( mm_formuliertest_paginas() as $post_id ) {
			$plek = array(
				'pagina'     => get_the_title( $post_id ),
				'url'        => (string) get_permalink( $post_id ),
				'queried_id' => $post_id,
			);
			mm_formuliertest_zoek( mm_formuliertest_elementen( $post_id ), $post_id, $plek, $gevonden );
			if ( count( $gevonden ) >= MM_FORMULIERTEST_MAX ) {
				break;
			}
		}
	}

	return array_slice( array_values( $gevonden ), 0, MM_FORMULIERTEST_MAX );
}

/**
 * Zoekt formulieren in de elementen van één document en zet ze in
 * $gevonden, met als sleutel waar het formulier echt staat. Gaat ook in
 * templates van de widget Template, met een grens tegen kringen.
 *
 * Per kandidaat: post_id en form_id zoals het formulier ze meestuurt,
 * queried_id, pagina (titel), url (voor de referrer) en sleutel.
 *
 * @param mixed $elementen Uit de Elementor-gegevens, dus eerst nakijken.
 * @param int   $post_id   Het document waar de elementen in staan.
 * @param array $plek      pagina, url en queried_id van de pagina.
 * @param array $gevonden
 * @param int   $diepte
 */
function mm_formuliertest_zoek( $elementen, $post_id, $plek, &$gevonden, $diepte = 0 ) {

	if ( ! is_array( $elementen ) || $diepte > 3 ) {
		return;
	}

	foreach ( $elementen as $element ) {

		if ( count( $gevonden ) >= MM_FORMULIERTEST_MAX ) {
			return;
		}

		if ( ! is_array( $element ) || empty( $element['id'] ) ) {
			continue;
		}

		$type    = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
		$sleutel = '';

		if ( 'form' === $type ) {
			// Staat submit_actions niet in de gegevens, dan geldt de standaard
			// van Elementor Pro: email.
			$acties = isset( $element['settings']['submit_actions'] ) ? (array) $element['settings']['submit_actions'] : MM_FORMULIERTEST_ACTIES;
			if ( ! array_intersect( MM_FORMULIERTEST_ACTIES, $acties ) ) {
				continue;
			}
			$sleutel = $post_id . ':' . $element['id'];
		} elseif ( 'global' === $type && ! empty( $element['templateID'] ) && 'form' === get_post_meta( (int) $element['templateID'], '_elementor_template_widget_type', true ) ) {
			$sleutel = 'global:' . (int) $element['templateID'];
		} elseif ( 'template' === $type && ! empty( $element['settings']['template_id'] ) ) {
			$template_id = (int) $element['settings']['template_id'];
			if ( $template_id !== (int) $post_id ) {
				mm_formuliertest_zoek( mm_formuliertest_elementen( $template_id ), $template_id, $plek, $gevonden, $diepte + 1 );
			}
			continue;
		}

		if ( '' !== $sleutel ) {
			if ( ! isset( $gevonden[ $sleutel ] ) ) {
				$gevonden[ $sleutel ] = array(
					'sleutel'    => $sleutel,
					'post_id'    => (int) $post_id,
					'form_id'    => (string) $element['id'],
					'queried_id' => (int) $plek['queried_id'],
					'pagina'     => (string) $plek['pagina'],
					'url'        => (string) $plek['url'],
				);
			}
			continue;
		}

		if ( ! empty( $element['elements'] ) ) {
			mm_formuliertest_zoek( $element['elements'], $post_id, $plek, $gevonden, $diepte );
		}
	}
}

/**
 * De elementen van een document, of een lege lijst.
 *
 * @param int $post_id
 * @return array
 */
function mm_formuliertest_elementen( $post_id ) {
	$document = \ElementorPro\Plugin::elementor()->documents->get( (int) $post_id );
	if ( ! $document ) {
		return array();
	}
	return $document->get_elements_data();
}

/**
 * Gepubliceerde pagina's en berichten met een formulier, een globale widget
 * of een Template-widget in de Elementor-gegevens. Pagina's eerst.
 *
 * @return int[]
 */
function mm_formuliertest_paginas() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Eén keer per maand; WP_Query kan niet zoeken in de JSON van _elementor_data.
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			WHERE p.post_type IN ( 'page', 'post' ) AND p.post_status = 'publish'
			AND ( m.meta_value LIKE %s OR m.meta_value LIKE %s OR m.meta_value LIKE %s )
			ORDER BY p.post_type = 'page' DESC, p.menu_order ASC, p.ID ASC
			LIMIT 200",
			'%' . $wpdb->esc_like( '"widgetType":"form"' ) . '%',
			'%' . $wpdb->esc_like( '"widgetType":"global"' ) . '%',
			'%' . $wpdb->esc_like( '"widgetType":"template"' ) . '%'
		)
	);

	return array_map( 'intval', (array) $ids );
}

/**
 * Theme Builder-templates en popups met een formulier die in gebruik zijn:
 * met weergavevoorwaarden, of (een popup) geopend vanuit een gepubliceerde
 * pagina, bericht of template via de dynamische tag Popup.
 *
 * @return array<int, array{id: int, titel: string}>
 */
function mm_formuliertest_templates_in_gebruik() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Eén keer per maand; WP_Query kan niet zoeken in de JSON van _elementor_data.
	$rijen = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, p.post_title, t.meta_value AS soort FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_elementor_template_type'
			INNER JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_elementor_data'
			WHERE p.post_type = 'elementor_library' AND p.post_status = 'publish'
			AND t.meta_value NOT IN ( 'page', 'section', 'container', 'widget', 'loop-item', 'kit' )
			AND ( d.meta_value LIKE %s OR d.meta_value LIKE %s OR d.meta_value LIKE %s )
			ORDER BY p.ID ASC
			LIMIT 50",
			'%' . $wpdb->esc_like( '"widgetType":"form"' ) . '%',
			'%' . $wpdb->esc_like( '"widgetType":"global"' ) . '%',
			'%' . $wpdb->esc_like( '"widgetType":"template"' ) . '%'
		)
	);

	$templates = array();

	foreach ( (array) $rijen as $rij ) {
		$id          = (int) $rij->ID;
		$voorwaarden = get_post_meta( $id, '_elementor_conditions', true );
		$in_gebruik  = ! empty( $voorwaarden );
		if ( ! $in_gebruik && 'popup' === $rij->soort ) {
			$in_gebruik = mm_formuliertest_popup_gebruikt( $id );
		}
		if ( $in_gebruik ) {
			$templates[] = array(
				'id'    => $id,
				'titel' => sprintf( '%s (%s)', $rij->post_title, $rij->soort ),
			);
		}
	}

	return $templates;
}

/**
 * Wordt de popup ergens geopend? De dynamische tag Popup staat in de
 * Elementor-gegevens als settings="%7B%22popup%22%3A%22123%22...".
 *
 * @param int $popup_id
 * @return bool
 */
function mm_formuliertest_popup_gebruikt( $popup_id ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Eén keer per maand per popup zonder voorwaarden.
	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = '_elementor_data' AND p.post_status = 'publish'
			AND m.meta_value LIKE %s
			LIMIT 1",
			'%' . $wpdb->esc_like( '%22popup%22%3A%22' . (int) $popup_id . '%22' ) . '%'
		)
	);
}


/* -------------------------------------------------------------------------
 * Eén formulier testen
 * ---------------------------------------------------------------------- */

/**
 * @param array $kandidaat Zie mm_formuliertest_zoek().
 * @return array{pagina: string, formulier: string, uitkomst: string, reden: string|null}|null
 *         Null als het formulier geen mailactie heeft.
 */
function mm_formuliertest_test( $kandidaat ) {

	$uitkomst = array(
		'pagina'    => $kandidaat['pagina'],
		'formulier' => '',
		'uitkomst'  => MM_FORMULIERTEST_NIET_TE_TESTEN,
		'reden'     => null,
	);

	$form = mm_formuliertest_formulier( $kandidaat );

	if ( is_string( $form ) ) {
		$uitkomst['reden'] = $form;
		return $uitkomst;
	}

	$instellingen          = $form['settings'];
	$uitkomst['formulier'] = isset( $instellingen['form_name'] ) && '' !== trim( (string) $instellingen['form_name'] ) ? trim( (string) $instellingen['form_name'] ) : $kandidaat['form_id'];

	// Zonder mailactie doet het formulier niet mee. Bij een globale widget is
	// dat pas hier te zien.
	$acties = array_values( array_intersect( MM_FORMULIERTEST_ACTIES, (array) ( isset( $instellingen['submit_actions'] ) ? $instellingen['submit_actions'] : array() ) ) );
	if ( ! $acties ) {
		return null;
	}

	$waarden = mm_formuliertest_waarden( isset( $instellingen['form_fields'] ) ? $instellingen['form_fields'] : array() );
	if ( is_string( $waarden ) ) {
		$uitkomst['reden'] = $waarden;
		return $uitkomst;
	}

	return array_merge( $uitkomst, mm_formuliertest_verstuur( $kandidaat, $form, $waarden, $acties ) );
}

/**
 * Zoekt het formulier op zoals ajax_send_form() dat doet.
 *
 * @param array $kandidaat
 * @return array|string Het formulier met de instellingen voor weergave, of
 *                      de reden waarom het niet te testen is.
 */
function mm_formuliertest_formulier( $kandidaat ) {

	$elementor = \ElementorPro\Plugin::elementor();
	$template  = null;
	$document  = $elementor->documents->get( $kandidaat['post_id'] );
	$form      = $document ? \ElementorPro\Modules\Forms\Module::find_element_recursive( $document->get_elements_data(), $kandidaat['form_id'] ) : false;

	if ( ! empty( $form['templateID'] ) ) {
		$template = $elementor->documents->get( $form['templateID'] );
		$data     = $template ? $template->get_elements_data() : array();
		$form     = isset( $data[0] ) ? $data[0] : false;
	}

	if ( empty( $form ) || ! is_array( $form ) ) {
		return 'formulier niet gevonden';
	}

	$widget = $elementor->elements_manager->create_element_instance( $form );
	if ( ! $widget ) {
		return 'formulier niet te laden';
	}

	$form['settings']                 = $widget->get_settings_for_display();
	$form['settings']['id']           = $kandidaat['form_id'];
	$form['settings']['form_post_id'] = $template ? $template->get_id() : $kandidaat['post_id'];
	$form['settings']['edit_post_id'] = $kandidaat['post_id'];

	if ( empty( $form['settings']['form_fields'] ) || ! is_array( $form['settings']['form_fields'] ) ) {
		return 'formulier zonder velden';
	}

	return $form;
}

/**
 * Het label van een veld, of anders de placeholder of het veld-ID.
 *
 * @param array $veld Een item uit form_fields.
 * @return string
 */
function mm_formuliertest_label( $veld ) {
	foreach ( array( 'field_label', 'placeholder', 'custom_id' ) as $sleutel ) {
		if ( isset( $veld[ $sleutel ] ) && '' !== trim( (string) $veld[ $sleutel ] ) ) {
			return trim( (string) $veld[ $sleutel ] );
		}
	}
	return '';
}

/**
 * De eerste optie van een keuzeveld met een waarde. Opties staan per regel,
 * als "Label" of "Label|waarde". Een eerste optie zonder waarde ("Maak een
 * keuze|") is een placeholder en wordt overgeslagen.
 *
 * @param string $opties
 * @return string
 */
function mm_formuliertest_eerste_optie( $opties ) {
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $opties ) as $optie ) {
		$waarde = false !== strpos( $optie, '|' ) ? substr( $optie, strpos( $optie, '|' ) + 1 ) : $optie;
		if ( '' !== trim( $waarde ) ) {
			return trim( $waarde );
		}
	}
	return '';
}

/**
 * De testwaarden voor alle velden, zoals het formulier ze als form_fields
 * meestuurt.
 *
 * @param array $velden form_fields uit de instellingen.
 * @return array|string De waarden per veld-ID, of de reden waarom het
 *                      formulier niet te testen is.
 */
function mm_formuliertest_waarden( $velden ) {

	$w       = MM_FORMULIERTEST_WAARDEN;
	$waarden = array();

	foreach ( $velden as $veld ) {

		if ( ! is_array( $veld ) || ! isset( $veld['custom_id'] ) ) {
			continue;
		}

		$id        = (string) $veld['custom_id'];
		$type      = isset( $veld['field_type'] ) ? (string) $veld['field_type'] : 'text';
		$verplicht = ! empty( $veld['required'] );
		$label     = mm_formuliertest_label( $veld );

		if ( preg_match( '/captcha|turnstile/i', $type ) ) {
			return sprintf( 'captcha (%s)', $type );
		}

		switch ( $type ) {
			case 'text':
				$waarden[ $id ] = mm_formuliertest_tekstwaarde( $label );
				break;
			case 'email':
				$waarden[ $id ] = $w['email'];
				break;
			case 'tel':
				$waarden[ $id ] = $w['telefoon'];
				break;
			case 'textarea':
				$waarden[ $id ] = $w['bericht'];
				break;
			case 'url':
				$waarden[ $id ] = 'https://mediamora.nl';
				break;
			case 'password':
				$waarden[ $id ] = $w['tekst'];
				break;
			case 'number':
				$waarden[ $id ] = isset( $veld['field_min'] ) && is_numeric( $veld['field_min'] ) ? (string) $veld['field_min'] : '1';
				break;
			case 'date':
				$waarden[ $id ] = wp_date( 'Y-m-d' );
				break;
			case 'time':
				$waarden[ $id ] = '12:00';
				break;
			case 'acceptance':
				$waarden[ $id ] = 'on';
				break;
			case 'select':
			case 'radio':
			case 'checkbox':
				$optie = mm_formuliertest_eerste_optie( isset( $veld['field_options'] ) ? $veld['field_options'] : '' );
				if ( '' === $optie && $verplicht ) {
					return sprintf( 'verplicht keuzeveld zonder opties (%s)', $label );
				}
				$waarden[ $id ] = ( 'checkbox' === $type || ! empty( $veld['allow_multiple'] ) ) ? array( $optie ) : $optie;
				break;
			case 'hidden':
				$waarden[ $id ] = isset( $veld['field_value'] ) ? (string) $veld['field_value'] : '';
				break;
			case 'honeypot':
				$waarden[ $id ] = '';
				break;
			case 'html':
			case 'step':
				break;
			case 'upload':
				if ( $verplicht ) {
					return sprintf( 'verplicht uploadveld (%s)', $label );
				}
				break;
			default:
				if ( $verplicht ) {
					return sprintf( 'verplicht veld van onbekend type %s (%s)', $type, $label );
				}
		}
	}

	return $waarden;
}

/**
 * De waarde voor een tekstveld, op het label.
 *
 * @param string $label
 * @return string
 */
function mm_formuliertest_tekstwaarde( $label ) {
	$w        = MM_FORMULIERTEST_WAARDEN;
	$postcode = (bool) preg_match( '/post ?code|zip/i', $label );
	$plaats   = (bool) preg_match( '/plaats|stad|city/i', $label );

	if ( preg_match( '/e-?mail/i', $label ) ) {
		return $w['email'];
	}
	if ( $postcode && $plaats ) {
		return $w['postcode'] . ' ' . $w['plaats'];
	}
	if ( $postcode ) {
		return $w['postcode'];
	}
	if ( $plaats ) {
		return $w['plaats'];
	}
	if ( preg_match( '/telefoon|phone|mobiel|\btel\b/i', $label ) ) {
		return $w['telefoon'];
	}
	if ( preg_match( '/naam|name/i', $label ) ) {
		return $w['naam'];
	}
	return $w['tekst'];
}

/**
 * Stuurt het formulier in en draait de mailacties, met de mail onderschept.
 *
 * @param array    $kandidaat
 * @param array    $form
 * @param array    $waarden
 * @param string[] $acties
 * @return array{uitkomst: string, reden: string|null}
 */
function mm_formuliertest_verstuur( $kandidaat, $form, $waarden, $acties ) {

	// Zoals het JavaScript van Elementor Pro het formulier instuurt, met de
	// referrer, zodat de referrercheck van de anti-spam meedoet.
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Alleen bewaard en daarna teruggezet; de test stuurt het formulier zelf in.
	$post_voor = $_POST;
	$_POST     = array(
		'post_id'       => (string) $kandidaat['post_id'],
		'form_id'       => $kandidaat['form_id'],
		'queried_id'    => (string) $kandidaat['queried_id'],
		'referrer'      => $kandidaat['url'],
		'referer_title' => $kandidaat['pagina'],
		'form_fields'   => $waarden,
	);
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$elementor = \ElementorPro\Plugin::elementor();
	$gewisseld = false;

	mm_formuliertest_weigering();
	mm_formuliertest_mail( null );
	mm_formuliertest_onderscheppen( true );

	try {
		// De pagina als globale post, voor dynamische waarden, zoals Elementor.
		$elementor->db->switch_to_post( $kandidaat['queried_id'] );
		$gewisseld = true;
		$uitkomst  = mm_formuliertest_verwerk( $form, $waarden, $acties );
	} catch ( \Throwable $e ) {
		$uitkomst = array(
			'uitkomst' => MM_FORMULIERTEST_MAIL_FOUT,
			'reden'    => mm_formuliertest_tekst( $e->getMessage() ),
		);
	} finally {
		mm_formuliertest_onderscheppen( false );
		if ( $gewisseld ) {
			$elementor->db->restore_current_post();
		}
		$_POST = $post_voor;
	}

	return $uitkomst;
}

/**
 * De verwerking van ajax_send_form() tot en met process_fields(), en dan
 * alleen de mailacties.
 *
 * @param array    $form
 * @param array    $waarden
 * @param string[] $acties
 * @return array{uitkomst: string, reden: string|null}
 */
function mm_formuliertest_verwerk( $form, $waarden, $acties ) {

	$handler = ( new \ReflectionClass( '\ElementorPro\Modules\Forms\Classes\Ajax_Handler' ) )->newInstanceWithoutConstructor();
	$record  = new \ElementorPro\Modules\Forms\Classes\Form_Record( $waarden, $form );

	$geldig      = $record->validate( $handler );
	$weigeringen = mm_formuliertest_weigering();

	if ( $weigeringen ) {
		return array(
			'uitkomst' => MM_FORMULIERTEST_GEWEIGERD,
			'reden'    => mm_formuliertest_redenen( $weigeringen ),
		);
	}

	if ( ! $geldig ) {
		return mm_formuliertest_validatiefout( $handler->errors, $form['settings']['form_fields'] );
	}

	$record->process_fields( $handler );
	if ( ! empty( $handler->errors ) ) {
		return mm_formuliertest_validatiefout( $handler->errors, $form['settings']['form_fields'] );
	}

	$geregistreerd = \ElementorPro\Modules\Forms\Module::instance()->actions_registrar->get();
	$fouten        = array();

	foreach ( $acties as $naam ) {

		$actie = isset( $geregistreerd[ $naam ] ) ? $geregistreerd[ $naam ] : null;
		if ( ! is_object( $actie ) ) {
			$fouten[] = $naam . ': actie niet geregistreerd';
			continue;
		}

		mm_formuliertest_mail( null );
		$melding = '';

		try {
			$actie->run( $record, $handler );
		} catch ( \Throwable $e ) {
			$melding = mm_formuliertest_tekst( $e->getMessage() );
		}

		// Een exception na wp_mail() komt van het onderscheppen zelf (een
		// plugin die wp_mail() vervangt). Telt alleen als er geen mail is.
		$fout = mm_formuliertest_mails_beoordelen( mm_formuliertest_mail( null ) );
		if ( '' !== $fout ) {
			$fouten[] = $naam . ': ' . ( 'wp_mail niet aangeroepen' === $fout && '' !== $melding ? $melding : $fout );
		}
	}

	if ( $fouten ) {
		return array(
			'uitkomst' => MM_FORMULIERTEST_MAIL_FOUT,
			'reden'    => implode( '; ', $fouten ),
		);
	}

	return array(
		'uitkomst' => MM_FORMULIERTEST_GESLAAGD,
		'reden'    => null,
	);
}

/**
 * De weigeringen van de anti-spam als één reden.
 *
 * @param array $weigeringen Zie mm_formuliertest_weigering().
 * @return string Bijvoorbeeld "onleesbare tekst (veld message)".
 */
function mm_formuliertest_redenen( $weigeringen ) {
	$redenen = array();
	foreach ( $weigeringen as $weigering ) {
		$redenen[] = $weigering['reden'] . ( '' !== $weigering['veld'] ? ' (veld ' . $weigering['veld'] . ')' : '' );
	}
	return implode( ', ', array_unique( $redenen ) );
}

/**
 * Een validatiefout, of niet te testen als de fout van een captcha komt die
 * niet als veld in het formulier staat, zoals een plugin die Turnstile zelf
 * in de validatie controleert: een fout bij een sleutel die geen veld is,
 * met captcha, Turnstile, robot of human in de melding.
 *
 * @param array $fouten Veld-ID => melding van Elementor.
 * @param array $velden form_fields.
 * @return array{uitkomst: string, reden: string|null}
 */
function mm_formuliertest_validatiefout( $fouten, $velden ) {

	$labels = array();
	foreach ( $velden as $veld ) {
		if ( is_array( $veld ) && isset( $veld['custom_id'] ) ) {
			$labels[ (string) $veld['custom_id'] ] = mm_formuliertest_label( $veld );
		}
	}

	$regels  = array();
	$captcha = true;

	foreach ( (array) $fouten as $id => $melding ) {
		$melding  = mm_formuliertest_tekst( is_scalar( $melding ) ? (string) $melding : '' );
		$captcha  = $captcha && ! isset( $labels[ (string) $id ] ) && (bool) preg_match( '/captcha|turnstile|robot|human/i', $melding );
		$regels[] = sprintf( 'veld %s: %s', isset( $labels[ (string) $id ] ) ? $labels[ (string) $id ] : (string) $id, $melding );
	}

	return array(
		'uitkomst' => $captcha && $regels ? MM_FORMULIERTEST_NIET_TE_TESTEN : MM_FORMULIERTEST_VALIDATIEFOUT,
		'reden'    => $regels ? implode( '; ', $regels ) : 'onbekende fout',
	);
}

/**
 * Een melding als korte platte tekst, zonder HTML en zonder adressen.
 *
 * @param string $tekst
 * @return string
 */
function mm_formuliertest_tekst( $tekst ) {
	$tekst = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $tekst ) ) );
	return mm_monitor_maskeer_adressen( function_exists( 'mb_substr' ) ? mb_substr( $tekst, 0, 200 ) : substr( $tekst, 0, 200 ) );
}


/* -------------------------------------------------------------------------
 * De mail onderscheppen
 *
 * Alleen tijdens de test van één formulier. Drie lagen:
 * - wp_mail (filter, als laatste): legt de opgebouwde mail vast en haalt
 *   daarna alle ontvangers eruit, ook Cc en Bcc in de koppen. Een plugin die
 *   de mail in een wachtrij zet of zelf verstuurt, heeft dan niemand meer.
 * - pre_wp_mail (filter, als eerste): wp_mail() stopt meteen en geeft true,
 *   zoals bij een geslaagde mail. Dan vuren wp_mail_succeeded en
 *   wp_mail_failed ook niet.
 * - phpmailer_init: komt alleen langs als een plugin pre_wp_mail overslaat.
 *   Haalt de ontvangers uit PHPMailer, zodat er niets de deur uit gaat.
 * ---------------------------------------------------------------------- */

/**
 * @param bool $aan
 */
function mm_formuliertest_onderscheppen( $aan ) {
	if ( $aan ) {
		add_filter( 'wp_mail', 'mm_formuliertest_wp_mail', PHP_INT_MAX );
		add_filter( 'pre_wp_mail', 'mm_formuliertest_pre_wp_mail', PHP_INT_MIN );
		add_action( 'phpmailer_init', 'mm_formuliertest_phpmailer', PHP_INT_MAX );
	} else {
		remove_filter( 'wp_mail', 'mm_formuliertest_wp_mail', PHP_INT_MAX );
		remove_filter( 'pre_wp_mail', 'mm_formuliertest_pre_wp_mail', PHP_INT_MIN );
		remove_action( 'phpmailer_init', 'mm_formuliertest_phpmailer', PHP_INT_MAX );
	}
}

/**
 * De mails die tijdens de test zijn opgebouwd.
 *
 * @param array|null $mail Null om de lijst te lezen en te legen.
 * @return array
 */
function mm_formuliertest_mail( $mail ) {
	static $mails = array();
	if ( null === $mail ) {
		$uit   = $mails;
		$mails = array();
		return $uit;
	}
	$mails[] = $mail;
	return $mails;
}

/**
 * @param mixed $atts to, subject, message, headers, attachments; komt van
 *                    andere plugins via het filter, dus eerst nakijken.
 * @return mixed
 */
function mm_formuliertest_wp_mail( $atts ) {

	if ( ! is_array( $atts ) ) {
		return $atts;
	}

	mm_formuliertest_mail(
		array(
			'to'      => isset( $atts['to'] ) ? $atts['to'] : '',
			'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
			'message' => isset( $atts['message'] ) ? (string) $atts['message'] : '',
		)
	);

	$koppen = isset( $atts['headers'] ) ? $atts['headers'] : array();
	$koppen = is_array( $koppen ) ? $koppen : preg_split( '/\r\n|\r|\n/', (string) $koppen );

	$atts['to']      = array();
	$atts['headers'] = array_values(
		array_filter(
			(array) $koppen,
			function ( $kop ) {
				return ! preg_match( '/^\s*b?cc\s*:/i', (string) $kop );
			}
		)
	);

	return $atts;
}

/**
 * @param null|bool $terug
 * @return bool
 */
function mm_formuliertest_pre_wp_mail( $terug ) {
	return true;
}

/**
 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
 */
function mm_formuliertest_phpmailer( $phpmailer ) {
	$phpmailer->clearAllRecipients();
}

/**
 * Is de mail van een actie goed opgebouwd? Minstens één mail, elk met
 * geldige ontvangers, een onderwerp en inhoud.
 *
 * @param array $mails Zie mm_formuliertest_wp_mail().
 * @return string Leeg als het goed is, anders de reden.
 */
function mm_formuliertest_mails_beoordelen( $mails ) {

	if ( ! $mails ) {
		return 'wp_mail niet aangeroepen';
	}

	foreach ( $mails as $mail ) {
		$adressen = is_array( $mail['to'] ) ? $mail['to'] : explode( ',', (string) $mail['to'] );
		$adressen = array_filter( array_map( 'trim', array_map( 'strval', $adressen ) ) );

		if ( ! $adressen ) {
			return 'geen ontvanger';
		}
		foreach ( $adressen as $adres ) {
			// "Naam <adres@domein.nl>" mag ook.
			if ( preg_match( '/<([^>]+)>\s*$/', $adres, $m ) ) {
				$adres = $m[1];
			}
			if ( ! is_email( $adres ) ) {
				return 'ongeldig ontvangstadres ' . mm_monitor_maskeer_adressen( $adres );
			}
		}
		if ( '' === trim( $mail['subject'] ) ) {
			return 'geen onderwerp';
		}
		if ( '' === trim( $mail['message'] ) ) {
			return 'geen inhoud';
		}
	}

	return '';
}


/* -------------------------------------------------------------------------
 * De mailroute
 * ---------------------------------------------------------------------- */

/**
 * Eén echte mail via wp_mail() naar MM_FORMULIERTEST_ONTVANGER. Nooit naar
 * MM_MONITOR_ONTVANGER: die mailbox moet alleen meldingen krijgen.
 *
 * @return array{uitkomst: string, melding: string|null}
 */
function mm_formuliertest_mailroute() {

	$ontvanger = defined( 'MM_FORMULIERTEST_ONTVANGER' ) ? trim( (string) constant( 'MM_FORMULIERTEST_ONTVANGER' ) ) : '';

	if ( '' === $ontvanger ) {
		return array(
			'uitkomst' => 'niet_getest',
			'melding'  => 'MM_FORMULIERTEST_ONTVANGER is niet gezet',
		);
	}
	if ( ! is_email( $ontvanger ) ) {
		return array(
			'uitkomst' => 'niet_getest',
			'melding'  => 'MM_FORMULIERTEST_ONTVANGER is geen geldig adres',
		);
	}
	if ( strtolower( $ontvanger ) === strtolower( trim( (string) MM_MONITOR_ONTVANGER ) ) ) {
		return array(
			'uitkomst' => 'niet_getest',
			'melding'  => 'MM_FORMULIERTEST_ONTVANGER is gelijk aan MM_MONITOR_ONTVANGER',
		);
	}

	$domein = mm_monitor_domein();
	$fout   = '';
	$vang   = function ( $wp_error ) use ( &$fout ) {
		if ( is_wp_error( $wp_error ) ) {
			$fout = $wp_error->get_error_message();
		}
	};

	add_action( 'wp_mail_failed', $vang );
	$gelukt = wp_mail(
		$ontvanger,
		sprintf( '[Formuliertest %s] mailroute', $domein ),
		implode(
			"\n",
			array(
				sprintf( 'Automatische maandelijkse test van de mailroute van %s, niet beantwoorden.', $domein ),
				sprintf( 'Verstuurd: %s (%s)', wp_date( 'Y-m-d H:i:s' ), wp_timezone_string() ),
			)
		),
		array(
			'Content-Type: text/plain; charset=UTF-8',
			'X-Mediamora-Monitor: 1',
			'X-Mediamora-Status: formuliertest',
			'X-Mediamora-Site: ' . $domein,
		)
	);
	remove_action( 'wp_mail_failed', $vang );

	if ( $gelukt ) {
		return array(
			'uitkomst' => 'geslaagd',
			'melding'  => null,
		);
	}

	return array(
		'uitkomst' => 'mislukt',
		'melding'  => '' !== $fout ? mm_formuliertest_tekst( $fout ) : 'wp_mail gaf false',
	);
}


/* -------------------------------------------------------------------------
 * De melding
 * ---------------------------------------------------------------------- */

/**
 * Een uitkomst als leesbare tekst.
 *
 * @param string $uitkomst
 * @return string
 */
function mm_formuliertest_uitkomst_tekst( $uitkomst ) {
	$teksten = array(
		MM_FORMULIERTEST_GESLAAGD       => 'geslaagd',
		MM_FORMULIERTEST_GEWEIGERD      => 'geweigerd door de anti-spam',
		MM_FORMULIERTEST_VALIDATIEFOUT  => 'validatiefout',
		MM_FORMULIERTEST_MAIL_FOUT      => 'mail niet op te bouwen',
		MM_FORMULIERTEST_NIET_TE_TESTEN => 'niet te testen',
		'mislukt'                       => 'mislukt',
		'niet_getest'                   => 'niet getest',
		'in_orde'                       => 'in orde',
	);
	return isset( $teksten[ $uitkomst ] ) ? $teksten[ $uitkomst ] : (string) $uitkomst;
}

/**
 * Eén melding per ronde naar MM_MONITOR_ONTVANGER, via mail() van de
 * Formuliermonitor: als wp_mail() kapot is, komt hij toch aan.
 *
 * @param array $ronde Zie mm_formuliertest_ronde().
 * @return bool
 */
function mm_formuliertest_melding( $ronde ) {

	$domein = mm_monitor_domein();

	$body   = array();
	$body[] = sprintf( 'Site:  %s', $domein );
	$body[] = sprintf( 'Ronde: %s (%s)%s', wp_date( 'Y-m-d H:i:s', $ronde['tijd'] ), wp_timezone_string(), $ronde['handmatig'] ? ', handmatig gestart' : '' );
	$body[] = '';
	$body[] = 'Formulieren:';

	if ( ! $ronde['formulieren'] ) {
		$body[] = '- geen formulieren met een mailactie gevonden';
	}
	foreach ( $ronde['formulieren'] as $formulier ) {
		$body[] = sprintf(
			'- %s, formulier "%s": %s%s',
			'' !== $formulier['pagina'] ? $formulier['pagina'] : '(zonder titel)',
			$formulier['formulier'],
			mm_formuliertest_uitkomst_tekst( $formulier['uitkomst'] ),
			null !== $formulier['reden'] ? ' (' . $formulier['reden'] . ')' : ''
		);
	}

	$body[] = '';
	$body[] = sprintf(
		'Mailroute: %s%s',
		mm_formuliertest_uitkomst_tekst( $ronde['mailroute']['uitkomst'] ),
		null !== $ronde['mailroute']['melding'] ? ' (' . $ronde['mailroute']['melding'] . ')' : ''
	);
	$body[] = '';
	$body[] = 'De testmails zijn onderschept: de klant heeft niets gekregen en er is niets opgeslagen.';

	return mm_monitor_mail( sprintf( '[Formuliermonitor] %s: formuliertest mislukt', $domein ), $body, 'formuliertest-mislukt' );
}


/* -------------------------------------------------------------------------
 * Status voor het MainWP-dashboard
 * ---------------------------------------------------------------------- */

/**
 * De laatste ronde voor de sync, zie includes/mainwp.php. Zonder ingevulde
 * waarden: die worden nergens bewaard.
 *
 * @return array
 */
function mm_formuliertest_sync_status() {
	$ronde    = get_option( MM_FORMULIERTEST_OPTIE, array() );
	$volgende = wp_next_scheduled( MM_FORMULIERTEST_HOOK );

	if ( ! is_array( $ronde ) || empty( $ronde['tijd'] ) ) {
		return array(
			'laatste_ronde'  => null,
			'handmatig'      => false,
			'volgende_ronde' => $volgende ? wp_date( DATE_ATOM, $volgende ) : null,
			'totaal'         => 'niet_getest',
			'mailroute'      => null,
			'formulieren'    => array(),
		);
	}

	$formulieren = array();
	foreach ( (array) $ronde['formulieren'] as $formulier ) {
		$formulieren[] = array(
			'pagina'    => (string) $formulier['pagina'],
			'formulier' => (string) $formulier['formulier'],
			'uitkomst'  => (string) $formulier['uitkomst'],
			'reden'     => null === $formulier['reden'] ? null : (string) $formulier['reden'],
		);
	}

	return array(
		'laatste_ronde'  => wp_date( DATE_ATOM, (int) $ronde['tijd'] ),
		'handmatig'      => ! empty( $ronde['handmatig'] ),
		'volgende_ronde' => $volgende ? wp_date( DATE_ATOM, $volgende ) : null,
		'totaal'         => (string) $ronde['totaal'],
		'mailroute'      => array(
			'uitkomst' => (string) $ronde['mailroute']['uitkomst'],
			'melding'  => null === $ronde['mailroute']['melding'] ? null : (string) $ronde['mailroute']['melding'],
		),
		'formulieren'    => $formulieren,
	);
}


/* -------------------------------------------------------------------------
 * Handmatig: de knop op het tabblad Mediamora
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_mm_formuliertest_nu', 'mm_formuliertest_knop' );

function mm_formuliertest_knop() {
	// De knop staat op het tabblad Mediamora, dus alleen voor Mediamora.
	if ( ! current_user_can( 'manage_options' ) || ! mm_toolkit_mediamora_gebruiker() ) {
		wp_die( 'Geen toegang.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mm_formuliertest_nu' );

	$ronde = mm_formuliertest_ronde( true );

	wp_safe_redirect( add_query_arg( 'mm_formuliertest', null === $ronde ? 'bezig' : 'klaar', mm_toolkit_scherm_url( 'mediamora' ) ) . '#mm-formuliertest' );
	exit;
}

/**
 * Korte regel voor de statuskolom van de Formuliermonitor.
 *
 * @return string HTML, al ge-escaped.
 */
function mm_formuliertest_status_regel() {
	$ronde    = get_option( MM_FORMULIERTEST_OPTIE, array() );
	$volgende = wp_next_scheduled( MM_FORMULIERTEST_HOOK );
	$na       = $volgende ? ', volgende ' . wp_date( 'j M H:i', $volgende ) : '';

	if ( ! is_array( $ronde ) || empty( $ronde['tijd'] ) ) {
		return esc_html( 'Formuliertest nog niet gedraaid' . $na );
	}

	$tekst = sprintf( 'Formuliertest %s: %s%s', wp_date( 'j M', (int) $ronde['tijd'] ), mm_formuliertest_uitkomst_tekst( $ronde['totaal'] ), $na );

	return 'mislukt' === $ronde['totaal'] ? '<span style="color:#b32d2e;">' . esc_html( $tekst ) . '</span>' : esc_html( $tekst );
}

/**
 * Het overzicht onder de tabel, met de knop. Eigen formulier, dus buiten
 * het formulier van de schakelaars.
 */
function mm_formuliertest_overzicht() {

	$ronde    = get_option( MM_FORMULIERTEST_OPTIE, array() );
	$volgende = wp_next_scheduled( MM_FORMULIERTEST_HOOK );

	echo '<h2 id="mm-formuliertest" style="margin-top:32px;">Formuliertest</h2>';
	echo '<p style="max-width:900px;color:#646970;">Elke maand gaan de Elementor-formulieren met een mailactie door de normale verwerking, met de anti-spam. De mail wordt opgebouwd en onderschept: de klant krijgt niets en er wordt niets opgeslagen. Daarnaast gaat er één mail naar <code>MM_FORMULIERTEST_ONTVANGER</code>. Alleen als er iets mislukt, komt er een melding.</p>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen gelezen om te bepalen wat er getoond wordt, er wordt niets opgeslagen.
	$gedaan = isset( $_GET['mm_formuliertest'] ) ? sanitize_key( wp_unslash( $_GET['mm_formuliertest'] ) ) : '';
	if ( 'klaar' === $gedaan ) {
		echo '<div class="notice notice-success inline"><p>Formuliertest gedraaid. Het vaste moment van de maand blijft staan.</p></div>';
	} elseif ( 'bezig' === $gedaan ) {
		echo '<div class="notice notice-warning inline"><p>Er liep al een formuliertest. Probeer het over een paar minuten opnieuw.</p></div>';
	}

	if ( ! is_array( $ronde ) || empty( $ronde['tijd'] ) ) {
		echo '<p>Nog niet gedraaid.</p>';
	} else {
		echo '<p>Laatste ronde ' . esc_html( wp_date( 'j F Y, H:i', (int) $ronde['tijd'] ) ) . ( $ronde['handmatig'] ? ' (handmatig)' : '' ) . ': <strong>' . esc_html( mm_formuliertest_uitkomst_tekst( $ronde['totaal'] ) ) . '</strong>.</p>';
		echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Pagina</th><th>Formulier</th><th>Uitkomst</th><th>Reden</th></tr></thead><tbody>';
		if ( ! $ronde['formulieren'] ) {
			echo '<tr><td colspan="4">Geen formulieren met een mailactie gevonden.</td></tr>';
		}
		foreach ( $ronde['formulieren'] as $formulier ) {
			$kleur = mm_formuliertest_mislukt( $formulier['uitkomst'] ) ? ' style="color:#b32d2e;"' : '';
			echo '<tr><td>' . esc_html( $formulier['pagina'] ) . '</td><td>' . esc_html( $formulier['formulier'] ) . '</td><td' . $kleur . '>' . esc_html( mm_formuliertest_uitkomst_tekst( $formulier['uitkomst'] ) ) . '</td><td>' . esc_html( (string) $formulier['reden'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $kleur is vaste HTML.
		}
		echo '</tbody></table>';
		echo '<p>Mailroute: ' . esc_html( mm_formuliertest_uitkomst_tekst( $ronde['mailroute']['uitkomst'] ) ) . ( null !== $ronde['mailroute']['melding'] ? ' (' . esc_html( $ronde['mailroute']['melding'] ) . ')' : '' ) . '.</p>';
	}

	if ( $volgende ) {
		echo '<p style="color:#646970;">Volgende ronde: ' . esc_html( wp_date( 'j F Y, H:i', $volgende ) ) . '.</p>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_formuliertest_nu">';
	wp_nonce_field( 'mm_formuliertest_nu' );
	submit_button( 'Nu testen', 'secondary', 'submit', false );
	echo ' <span style="color:#646970;">Verschuift het vaste moment van de maand niet.</span>';
	echo '</form>';
}


/* -------------------------------------------------------------------------
 * Handmatig: WP-CLI
 *
 *   wp mediamora formuliertest            een ronde draaien
 *   wp mediamora formuliertest --status   alleen de laatste ronde tonen
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mediamora formuliertest', 'mm_formuliertest_cli' );
}

/**
 * @param array $args
 * @param array $assoc_args
 */
function mm_formuliertest_cli( $args, $assoc_args ) {

	if ( empty( $assoc_args['status'] ) ) {
		if ( null === mm_formuliertest_ronde( true ) ) {
			WP_CLI::error( 'Er loopt al een formuliertest.' );
		}
	}

	$status = mm_formuliertest_sync_status();

	if ( null === $status['laatste_ronde'] ) {
		WP_CLI::log( 'Nog niet gedraaid.' );
	} else {
		WP_CLI::log( sprintf( 'Ronde %s%s', $status['laatste_ronde'], $status['handmatig'] ? ' (handmatig)' : '' ) );
		foreach ( $status['formulieren'] as $formulier ) {
			WP_CLI::log(
				sprintf(
					'- %s, formulier "%s": %s%s',
					$formulier['pagina'],
					$formulier['formulier'],
					mm_formuliertest_uitkomst_tekst( $formulier['uitkomst'] ),
					null !== $formulier['reden'] ? ' (' . $formulier['reden'] . ')' : ''
				)
			);
		}
		WP_CLI::log( sprintf( 'Mailroute: %s%s', mm_formuliertest_uitkomst_tekst( $status['mailroute']['uitkomst'] ), null !== $status['mailroute']['melding'] ? ' (' . $status['mailroute']['melding'] . ')' : '' ) );
	}

	WP_CLI::log( sprintf( 'Volgende ronde: %s', null !== $status['volgende_ronde'] ? $status['volgende_ronde'] : 'niet ingepland' ) );

	if ( 'mislukt' === $status['totaal'] ) {
		$ronde = get_option( MM_FORMULIERTEST_OPTIE, array() );
		WP_CLI::warning( 'Totaal: mislukt.' . ( ! empty( $ronde['gemeld'] ) ? ' Melding gestuurd naar ' . MM_MONITOR_ONTVANGER . '.' : ' De melding kon niet verstuurd worden.' ) );
	} else {
		WP_CLI::success( 'Totaal: ' . mm_formuliertest_uitkomst_tekst( $status['totaal'] ) . '.' );
	}
}
