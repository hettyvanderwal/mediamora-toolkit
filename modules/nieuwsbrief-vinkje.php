<?php
/**
 * Mediamora Toolkit, module: Formulieren: nieuwsbrief alleen bij vinkje
 * Vervangt losse code in functions.php van het child theme (sanneburen.nl).
 * Wordt alleen geladen als de module aanstaat.
 *
 * Deel A, voorwaardelijke koppeling. Elementor Pro voert de koppelingen met
 * ActiveCampaign (ook MailBlue, dezelfde API) en MailerLite bij elke inzending
 * uit. Deze module geeft die twee in de editor een tekstveld "Alleen uitvoeren
 * als dit veld is aangevinkt", per koppeling, want een formulier kan beide
 * gebruiken. Staat daar een veld-ID en is dat veld niet aangevinkt, dan haalt
 * de module de koppeling voor die ene inzending uit de lijst met acties, via
 * het filter elementor_pro/forms/submit_actions (sinds Elementor Pro 4.0.3).
 * Geen Exception en geen melding voor de bezoeker; met WP_DEBUG_LOG aan wel
 * een regel in de debuglog. De klassen van Elementor blijven ongemoeid, dus
 * veldkoppelingen, lijstkeuze en API-sleutel werken zoals altijd.
 *
 * Bestaat het veld-ID niet in het formulier, dan draait de koppeling gewoon en
 * komt er een melding in het beheer, zodat een tikfout geen inschrijvingen
 * kost. De melding verdwijnt zodra dat formulier weer zonder tikfout is
 * ingezonden, of met de link in de melding.
 *
 * De controle kijkt naar raw_value, niet naar value: deel B zet value op "Nee".
 *
 * Deel B, lege vinkvelden als "Nee". Elementor geeft een veld dat niet is
 * meegestuurd value '' en raw_value '', dus een niet-aangevinkt vinkveld stond
 * als "Nieuwsbrief: " in de mail. Op elementor_pro/forms/record/actions_before,
 * voor alle formulieren van de site, krijgt elk acceptatieveld en elke checkbox
 * met precies één optie value "Nee" als raw_value leeg is. Een aangevinkte
 * acceptatie ("on") wordt "Ja". raw_value blijft zoals hij was. Checkboxen met
 * meer opties en andere veldtypes blijven ongemoeid. De mail ([all-fields] en
 * [field id="..."]), de opgeslagen inzending en de koppelingen lezen value na
 * dit filter, dus overal staat Nee of Ja. Gaat een acceptatieveld via de
 * veldkoppeling naar ActiveCampaign of MailerLite, dan krijgt die dus "Ja" in
 * plaats van "on".
 *
 * Botst niet met de anti-spam: die draait eerder, op
 * elementor_pro/forms/validation, en ziet de velden nog zoals ze binnenkwamen.
 * De Formuliermonitor hangt aan wp_mail_failed en merkt hier niets van.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Vanaf deze versie heeft Elementor Pro het filter submit_actions. */
const MM_NIEUWSBRIEF_EP_MINIMUM = '4.0.3';

/** Optie met de meldingen over veld-ID's die niet in het formulier staan. */
const MM_NIEUWSBRIEF_MELDINGEN = 'mm_nieuwsbrief_vinkje_meldingen';

/**
 * De koppelingen die een vinkveld kunnen krijgen: actienaam in Elementor Pro,
 * de sectie in de editor, de eigen instelling en de naam voor meldingen.
 */
function mm_nieuwsbrief_koppelingen() {
	return array(
		'activecampaign' => array(
			'sectie'     => 'section_activecampaign',
			'instelling' => 'mm_activecampaign_vinkveld',
			'naam'       => 'ActiveCampaign',
		),
		'mailerlite'     => array(
			'sectie'     => 'section_mailerlite',
			'instelling' => 'mm_mailerlite_vinkveld',
			'naam'       => 'MailerLite',
		),
	);
}

/**
 * Heeft deze Elementor Pro het filter submit_actions?
 */
function mm_nieuwsbrief_ep_geschikt() {
	return defined( 'ELEMENTOR_PRO_VERSION' ) && version_compare( ELEMENTOR_PRO_VERSION, MM_NIEUWSBRIEF_EP_MINIMUM, '>=' );
}

/**
 * Het veld-ID dat bij een koppeling is ingevuld, of ''.
 *
 * @param array  $instellingen De instellingen van het formulier.
 * @param string $actie        Actienaam uit mm_nieuwsbrief_koppelingen().
 * @return string
 */
function mm_nieuwsbrief_veld_id( $instellingen, $actie ) {

	$koppelingen = mm_nieuwsbrief_koppelingen();
	$naam        = $koppelingen[ $actie ]['instelling'];

	return isset( $instellingen[ $naam ] ) && is_string( $instellingen[ $naam ] ) ? trim( $instellingen[ $naam ] ) : '';
}

/**
 * Is deze raw_value leeg? Een checkbox komt als array binnen, een acceptatie
 * als "on", en een veld dat niet is meegestuurd als ''.
 *
 * @param mixed $waarde
 * @return bool
 */
function mm_nieuwsbrief_leeg( $waarde ) {

	if ( is_array( $waarde ) ) {
		foreach ( $waarde as $deel ) {
			if ( ! mm_nieuwsbrief_leeg( $deel ) ) {
				return false;
			}
		}
		return true;
	}

	return '' === trim( (string) $waarde );
}

/**
 * Wat gebeurt er met een koppeling?
 *
 * @param array  $velden  De velden van het record, per ID.
 * @param string $veld_id De instelling bij de koppeling.
 * @return string 'altijd' (geen veld-ID), 'uitvoeren' (aangevinkt),
 *                'overslaan' (niet aangevinkt) of 'onbekend' (veld bestaat
 *                niet, dus uitvoeren met een melding).
 */
function mm_nieuwsbrief_beslissing( $velden, $veld_id ) {

	$veld_id = trim( (string) $veld_id );

	if ( '' === $veld_id ) {
		return 'altijd';
	}

	if ( ! is_array( $velden ) || ! isset( $velden[ $veld_id ] ) || ! is_array( $velden[ $veld_id ] ) ) {
		return 'onbekend';
	}

	$raw = isset( $velden[ $veld_id ]['raw_value'] ) ? $velden[ $veld_id ]['raw_value'] : '';

	return mm_nieuwsbrief_leeg( $raw ) ? 'overslaan' : 'uitvoeren';
}

/**
 * De lijst met acties na de controle, plus per koppeling de beslissing.
 *
 * @param string[] $acties       Actienamen uit submit_actions.
 * @param array    $velden       De velden van het record, per ID.
 * @param array    $instellingen De instellingen van het formulier.
 * @return array array( acties, beslissingen ). Beslissingen alleen voor de
 *               koppelingen die in de lijst stonden.
 */
function mm_nieuwsbrief_acties( $acties, $velden, $instellingen ) {

	if ( ! is_array( $acties ) ) {
		return array( $acties, array() );
	}

	$beslissingen = array();

	foreach ( mm_nieuwsbrief_koppelingen() as $actie => $koppeling ) {
		if ( ! in_array( $actie, $acties, true ) ) {
			continue;
		}
		$beslissingen[ $actie ] = mm_nieuwsbrief_beslissing( $velden, mm_nieuwsbrief_veld_id( $instellingen, $actie ) );
	}

	$overslaan = array_keys( $beslissingen, 'overslaan', true );

	if ( $overslaan ) {
		$acties = array_values(
			array_filter(
				$acties,
				function ( $actie ) use ( $overslaan ) {
					return ! in_array( $actie, $overslaan, true );
				}
			)
		);
	}

	return array( $acties, $beslissingen );
}

/**
 * Hoeveel opties heeft deze checkbox? Elementor zet ze onder elkaar in
 * field_options, als "tekst" of "tekst|waarde". Lege regels tellen niet.
 *
 * @param mixed $opties
 * @return int
 */
function mm_nieuwsbrief_aantal_opties( $opties ) {

	$regels = preg_split( "/\r\n|\r|\n/", (string) $opties );

	return count(
		array_filter(
			$regels,
			function ( $regel ) {
				return '' !== trim( $regel );
			}
		)
	);
}

/**
 * Welke velden krijgen Nee of Ja?
 *
 * @param array $velden       De velden van het record, per ID.
 * @param array $form_fields  form_fields uit de instellingen van het formulier.
 * @return string[] Per veld-ID de nieuwe value.
 */
function mm_nieuwsbrief_ja_nee( $velden, $form_fields ) {

	$opties = array();
	foreach ( (array) $form_fields as $form_field ) {
		if ( is_array( $form_field ) && isset( $form_field['custom_id'] ) ) {
			$opties[ $form_field['custom_id'] ] = isset( $form_field['field_options'] ) ? $form_field['field_options'] : '';
		}
	}

	$nieuw = array();

	foreach ( (array) $velden as $id => $veld ) {

		if ( ! is_array( $veld ) || ! isset( $veld['type'] ) ) {
			continue;
		}

		$leeg = mm_nieuwsbrief_leeg( isset( $veld['raw_value'] ) ? $veld['raw_value'] : '' );

		if ( 'acceptance' === $veld['type'] ) {
			$nieuw[ $id ] = $leeg ? 'Nee' : 'Ja';
		} elseif ( 'checkbox' === $veld['type'] && $leeg && 1 === mm_nieuwsbrief_aantal_opties( isset( $opties[ $id ] ) ? $opties[ $id ] : '' ) ) {
			$nieuw[ $id ] = 'Nee';
		}
	}

	return $nieuw;
}

/* -------------------------------------------------------------------------
 * Deel A: de instelling in de editor en de controle bij het inzenden
 * ---------------------------------------------------------------------- */

foreach ( mm_nieuwsbrief_koppelingen() as $mm_nieuwsbrief_koppeling ) {
	add_action( 'elementor/element/form/' . $mm_nieuwsbrief_koppeling['sectie'] . '/before_section_end', 'mm_nieuwsbrief_instelling', 10, 2 );
}
unset( $mm_nieuwsbrief_koppeling );

/**
 * Zet het tekstveld onderaan de sectie van de koppeling. Op een te oude
 * Elementor Pro niet, want daar zou de instelling niets doen.
 *
 * @param \Elementor\Controls_Stack $widget
 * @param array                     $args
 */
function mm_nieuwsbrief_instelling( $widget, $args ) {

	if ( ! mm_nieuwsbrief_ep_geschikt() || ! is_object( $widget ) || ! method_exists( $widget, 'add_control' ) ) {
		return;
	}

	$sectie = (string) current_filter();

	foreach ( mm_nieuwsbrief_koppelingen() as $koppeling ) {
		if ( false === strpos( $sectie, '/' . $koppeling['sectie'] . '/' ) ) {
			continue;
		}
		$widget->add_control(
			$koppeling['instelling'],
			array(
				'label'       => 'Alleen uitvoeren als dit veld is aangevinkt',
				'type'        => 'text',
				'description' => 'Vul het veld-ID in, bijv. nieuwsbrief. Leeg = altijd uitvoeren.',
				'label_block' => true,
				'separator'   => 'before',
				'ai'          => array( 'active' => false ),
			)
		);
	}
}

add_filter( 'elementor_pro/forms/submit_actions', 'mm_nieuwsbrief_submit_actions', 10, 2 );

/**
 * Haalt een koppeling uit de lijst als het vinkveld leeg is.
 *
 * @param string[]                                       $acties
 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
 * @return string[]
 */
function mm_nieuwsbrief_submit_actions( $acties, $record ) {

	if ( ! is_array( $acties ) || ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
		return $acties;
	}

	$instellingen = $record->get( 'form_settings' );
	$instellingen = is_array( $instellingen ) ? $instellingen : array();

	list( $na, $beslissingen ) = mm_nieuwsbrief_acties( $acties, $record->get( 'fields' ), $instellingen );

	$koppelingen = mm_nieuwsbrief_koppelingen();

	foreach ( $beslissingen as $actie => $beslissing ) {

		$veld_id = mm_nieuwsbrief_veld_id( $instellingen, $actie );

		if ( 'overslaan' === $beslissing && defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'Mediamora Toolkit, nieuwsbrief alleen bij vinkje: %s overgeslagen voor formulier "%s" (post %d), veld "%s" is niet aangevinkt.',
					$koppelingen[ $actie ]['naam'],
					isset( $instellingen['form_name'] ) ? (string) $instellingen['form_name'] : '',
					isset( $instellingen['form_post_id'] ) ? (int) $instellingen['form_post_id'] : 0,
					$veld_id
				)
			);
		}

		mm_nieuwsbrief_melding_bijwerken( $actie, $instellingen, 'onbekend' === $beslissing ? $veld_id : '' );
	}

	return $na;
}

/**
 * Zet of wist de melding voor één koppeling in één formulier. Schrijft alleen
 * als er iets verandert, dus een gewone inzending kost geen schrijfactie.
 *
 * @param string $actie
 * @param array  $instellingen
 * @param string $veld_id      Het onbekende veld-ID, of '' om te wissen.
 */
function mm_nieuwsbrief_melding_bijwerken( $actie, $instellingen, $veld_id ) {

	$post_id = isset( $instellingen['form_post_id'] ) ? (int) $instellingen['form_post_id'] : 0;
	$form_id = isset( $instellingen['id'] ) ? (string) $instellingen['id'] : '';
	$sleutel = $post_id . '|' . $form_id . '|' . $actie;

	$meldingen = get_option( MM_NIEUWSBRIEF_MELDINGEN, array() );
	$meldingen = is_array( $meldingen ) ? $meldingen : array();

	if ( '' === $veld_id ) {
		if ( isset( $meldingen[ $sleutel ] ) ) {
			unset( $meldingen[ $sleutel ] );
			$meldingen ? update_option( MM_NIEUWSBRIEF_MELDINGEN, $meldingen, false ) : delete_option( MM_NIEUWSBRIEF_MELDINGEN );
		}
		return;
	}

	if ( isset( $meldingen[ $sleutel ]['veld'] ) && $meldingen[ $sleutel ]['veld'] === $veld_id ) {
		return;
	}

	$meldingen[ $sleutel ] = array(
		'post'  => $post_id,
		'naam'  => isset( $instellingen['form_name'] ) ? (string) $instellingen['form_name'] : '',
		'actie' => $actie,
		'veld'  => $veld_id,
		'tijd'  => time(),
	);

	// Niet eindeloos laten groeien als er formulieren verdwijnen.
	$meldingen = array_slice( $meldingen, -20, null, true );

	update_option( MM_NIEUWSBRIEF_MELDINGEN, $meldingen, false );
}

/* -------------------------------------------------------------------------
 * Deel B: lege vinkvelden als Nee
 * ---------------------------------------------------------------------- */

add_filter( 'elementor_pro/forms/record/actions_before', 'mm_nieuwsbrief_record' );

/**
 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
 * @return \ElementorPro\Modules\Forms\Classes\Form_Record
 */
function mm_nieuwsbrief_record( $record ) {

	if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) || ! method_exists( $record, 'update_field' ) ) {
		return $record;
	}

	$instellingen = $record->get( 'form_settings' );
	$form_fields  = is_array( $instellingen ) && isset( $instellingen['form_fields'] ) ? $instellingen['form_fields'] : array();

	foreach ( mm_nieuwsbrief_ja_nee( $record->get( 'fields' ), $form_fields ) as $id => $waarde ) {
		$record->update_field( $id, 'value', $waarde );
	}

	return $record;
}

/* -------------------------------------------------------------------------
 * Meldingen in het beheer
 * ---------------------------------------------------------------------- */

add_action( 'admin_notices', 'mm_nieuwsbrief_meldingen' );

function mm_nieuwsbrief_meldingen() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( defined( 'ELEMENTOR_PRO_VERSION' ) && ! mm_nieuwsbrief_ep_geschikt() ) {
		echo '<div class="notice notice-warning"><p><strong>Mediamora Toolkit:</strong> Formulieren: nieuwsbrief alleen bij vinkje werkt vanaf Elementor Pro ' . esc_html( MM_NIEUWSBRIEF_EP_MINIMUM ) . ', en deze site heeft ' . esc_html( ELEMENTOR_PRO_VERSION ) . '. Lege vinkvelden worden wel als Nee getoond, maar ActiveCampaign en MailerLite draaien bij elke inzending. Werk Elementor Pro bij.</p></div>';
	}

	$meldingen = get_option( MM_NIEUWSBRIEF_MELDINGEN, array() );

	if ( ! is_array( $meldingen ) || ! $meldingen ) {
		return;
	}

	$koppelingen = mm_nieuwsbrief_koppelingen();
	$regels      = array();

	foreach ( $meldingen as $melding ) {
		if ( ! is_array( $melding ) || ! isset( $melding['actie'], $koppelingen[ $melding['actie'] ] ) ) {
			continue;
		}
		$post  = (int) $melding['post'];
		$titel = $post ? get_the_title( $post ) : '';
		$waar  = $melding['naam'] ? 'het formulier "' . esc_html( $melding['naam'] ) . '"' : 'een formulier';
		if ( $post ) {
			$waar .= ' op <a href="' . esc_url( admin_url( 'post.php?post=' . $post . '&action=elementor' ) ) . '">' . esc_html( '' !== $titel ? $titel : 'post ' . $post ) . '</a>';
		}
		$regels[] = $waar . ': bij ' . esc_html( $koppelingen[ $melding['actie'] ]['naam'] ) . ' staat het veld-ID <code>' . esc_html( $melding['veld'] ) . '</code>, maar dat veld bestaat niet in het formulier.';
	}

	if ( ! $regels ) {
		return;
	}

	$wegklikken = wp_nonce_url( admin_url( 'admin-post.php?action=mm_nieuwsbrief_wegklikken' ), 'mm_nieuwsbrief_wegklikken' );

	echo '<div class="notice notice-warning"><p><strong>Mediamora Toolkit:</strong> de nieuwsbriefkoppeling draait bij elke inzending, ook zonder vinkje, want het veld-ID klopt niet.</p><ul style="list-style:disc;padding-left:20px;">';
	foreach ( $regels as $regel ) {
		echo '<li>' . $regel . '</li>';
	}
	echo '</ul><p>Pas het veld-ID aan of maak het leeg. De melding verdwijnt na de volgende inzending van dat formulier. <a href="' . esc_url( $wegklikken ) . '">Melding wegklikken</a></p></div>';
}

add_action( 'admin_post_mm_nieuwsbrief_wegklikken', 'mm_nieuwsbrief_wegklikken' );

function mm_nieuwsbrief_wegklikken() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.', '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'mm_nieuwsbrief_wegklikken' );
	delete_option( MM_NIEUWSBRIEF_MELDINGEN );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
