<?php
/**
 * Mediamora Toolkit, testgevallen voor Formulieren: nieuwsbrief alleen bij
 * vinkje. Deel A (koppeling wel of niet uitvoeren) en deel B (Nee of Ja).
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/nieuwsbrief-vinkje.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * Het record hieronder bouwt de velden op zoals Form_Record::set_fields() in
 * Elementor Pro 4.3.3: een veld dat niet is meegestuurd krijgt value '' en
 * raw_value '', een checkbox komt als array binnen en wordt in value met
 * komma's samengevoegd.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ELEMENTOR_PRO_VERSION', '4.3.3' );

$GLOBALS['opties'] = array();
$GLOBALS['hooks']  = array();
$GLOBALS['log']    = array();

function add_action( $hook, $callback, $prioriteit = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = $callback;
}
function add_filter( $hook, $callback, $prioriteit = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ] = $callback;
}
function get_option( $naam, $standaard = false ) {
	return array_key_exists( $naam, $GLOBALS['opties'] ) ? $GLOBALS['opties'][ $naam ] : $standaard;
}
function update_option( $naam, $waarde, $autoload = null ) {
	$GLOBALS['opties'][ $naam ] = $waarde;
	$GLOBALS['schrijven']       = ( $GLOBALS['schrijven'] ?? 0 ) + 1;
	return true;
}
function delete_option( $naam ) {
	unset( $GLOBALS['opties'][ $naam ] );
	return true;
}

require dirname( __DIR__ ) . '/modules/nieuwsbrief-vinkje.php';

class Nep_Record {
	private $fields   = array();
	private $settings = array();

	public function __construct( $verstuurd, $form_fields, $settings = array() ) {
		$this->settings = array_merge(
			array(
				'id'           => 'abc1234',
				'form_name'    => 'Contact',
				'form_post_id' => 891,
				'edit_post_id' => 891,
				'form_fields'  => $form_fields,
			),
			$settings
		);
		foreach ( $form_fields as $form_field ) {
			$veld = array(
				'id'        => $form_field['custom_id'],
				'type'      => $form_field['field_type'],
				'title'     => $form_field['field_label'],
				'value'     => '',
				'raw_value' => '',
			);
			if ( isset( $verstuurd[ $form_field['custom_id'] ] ) ) {
				$veld['raw_value'] = $verstuurd[ $form_field['custom_id'] ];
				$veld['value']     = is_array( $veld['raw_value'] ) ? implode( ', ', $veld['raw_value'] ) : $veld['raw_value'];
			}
			$this->fields[ $form_field['custom_id'] ] = $veld;
		}
	}

	public function get( $wat ) {
		return 'fields' === $wat ? $this->fields : ( 'form_settings' === $wat ? $this->settings : null );
	}

	public function update_field( $field_id, $property, $value ) {
		if ( ! isset( $this->fields[ $field_id ] ) || ! isset( $this->fields[ $field_id ][ $property ] ) ) {
			return;
		}
		$this->fields[ $field_id ][ $property ] = $value;
	}
}

function veld( $id, $type, $label, $opties = '' ) {
	return array( 'custom_id' => $id, 'field_type' => $type, 'field_label' => $label, 'field_options' => $opties );
}

$form_fields = array(
	veld( 'name', 'text', 'Naam' ),
	veld( 'email', 'email', 'E-mail' ),
	veld( 'nieuwsbrief', 'checkbox', 'Nieuwsbrief', 'Ja, ik wil de nieuwsbrief|Ja' ),
	veld( 'akkoord', 'acceptance', 'Akkoord' ),
	veld( 'interesses', 'checkbox', 'Interesses', "Yoga\nMeditatie" ),
	veld( 'keuze', 'radio', 'Keuze', 'Ja' ),
);

$fout = 0;
function klopt( $naam, $uitkomst, $verwacht ) {
	global $fout;
	if ( $uitkomst === $verwacht ) {
		echo "ok    $naam\n";
		return;
	}
	$fout++;
	echo "FOUT  $naam\n      verwacht: " . var_export( $verwacht, true ) . "\n      kreeg:    " . var_export( $uitkomst, true ) . "\n";
}

/**
 * Doet wat ajax_send_form doet: eerst actions_before, dan submit_actions.
 */
function inzenden( $verstuurd, $acties, $settings = array() ) {
	global $form_fields;
	$record = new Nep_Record( $verstuurd, $form_fields, array_merge( array( 'submit_actions' => $acties ), $settings ) );
	$record = $GLOBALS['hooks']['elementor_pro/forms/record/actions_before']( $record, null );
	$na     = $GLOBALS['hooks']['elementor_pro/forms/submit_actions']( $acties, $record, null );
	return array( $na, $record->get( 'fields' ) );
}

$alle = array( 'save-to-database', 'email', 'activecampaign', 'mailerlite' );
$ac   = array( 'mm_activecampaign_vinkveld' => 'nieuwsbrief' );
$beide = array( 'mm_activecampaign_vinkveld' => 'nieuwsbrief', 'mm_mailerlite_vinkveld' => 'akkoord' );

// Deel A: wel of niet uitvoeren.
list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), $alle, $ac );
klopt( 'zonder vinkje: ActiveCampaign eruit, de rest blijft', $na, array( 'save-to-database', 'email', 'mailerlite' ) );

list( $na ) = inzenden( array( 'email' => 'a@b.nl', 'nieuwsbrief' => array( 'Ja' ) ), $alle, $ac );
klopt( 'met vinkje: alles blijft', $na, $alle );

list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), $alle );
klopt( 'geen veld-ID: alles blijft', $na, $alle );

list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'mm_activecampaign_vinkveld' => '   ' ) );
klopt( 'alleen spaties als veld-ID: alles blijft', $na, $alle );

list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'mm_activecampaign_vinkveld' => ' nieuwsbrief ' ) );
klopt( 'veld-ID met spaties eromheen telt als nieuwsbrief', $na, array( 'save-to-database', 'email', 'mailerlite' ) );

list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), $alle, $beide );
klopt( 'beide zonder vinkje: beide eruit', $na, array( 'save-to-database', 'email' ) );

list( $na ) = inzenden( array( 'email' => 'a@b.nl', 'akkoord' => 'on' ), $alle, $beide );
klopt( 'alleen acceptatie aangevinkt: alleen MailerLite blijft', $na, array( 'save-to-database', 'email', 'mailerlite' ) );

list( $na ) = inzenden( array( 'email' => 'a@b.nl', 'nieuwsbrief' => array( '' ) ), $alle, $ac );
klopt( 'checkbox met lege waarde telt als leeg', $na, array( 'save-to-database', 'email', 'mailerlite' ) );

list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), array( 'email' ), $ac );
klopt( 'koppeling niet gekozen: lijst ongewijzigd', $na, array( 'email' ) );

list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'mm_activecampaign_vinkveld' => array( 'x' ) ) );
klopt( 'instelling geen tekst: altijd uitvoeren', $na, $alle );

// Deel A: veld-ID bestaat niet.
$GLOBALS['opties'] = array();
list( $na ) = inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'mm_activecampaign_vinkveld' => 'nieuwbrief' ) );
klopt( 'tikfout: koppeling draait', $na, $alle );
$meldingen = get_option( MM_NIEUWSBRIEF_MELDINGEN, array() );
klopt( 'tikfout: één melding', count( $meldingen ), 1 );
$melding = reset( $meldingen );
klopt( 'tikfout: melding noemt het veld-ID', $melding['veld'], 'nieuwbrief' );
klopt( 'tikfout: melding noemt de koppeling', $melding['actie'], 'activecampaign' );
klopt( 'tikfout: melding noemt de post', $melding['post'], 891 );

$GLOBALS['schrijven'] = 0;
inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'mm_activecampaign_vinkveld' => 'nieuwbrief' ) );
klopt( 'zelfde tikfout nog eens: niet opnieuw schrijven', $GLOBALS['schrijven'], 0 );

inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'mm_activecampaign_vinkveld' => 'nieuwbrief', 'form_post_id' => 12, 'id' => 'xyz' ) );
klopt( 'tikfout in een ander formulier: twee meldingen', count( get_option( MM_NIEUWSBRIEF_MELDINGEN, array() ) ), 2 );

inzenden( array( 'email' => 'a@b.nl' ), $alle, $ac );
klopt( 'tikfout hersteld: melding van dat formulier weg', count( get_option( MM_NIEUWSBRIEF_MELDINGEN, array() ) ), 1 );

inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'form_post_id' => 12, 'id' => 'xyz' ) );
klopt( 'veld-ID leeggemaakt: laatste melding weg, optie weg', array_key_exists( MM_NIEUWSBRIEF_MELDINGEN, $GLOBALS['opties'] ), false );

$GLOBALS['schrijven'] = 0;
inzenden( array( 'email' => 'a@b.nl', 'nieuwsbrief' => array( 'Ja' ) ), $alle, $ac );
klopt( 'gewone inzending: geen schrijfactie', $GLOBALS['schrijven'], 0 );

// Deel B: Nee en Ja.
list( , $velden ) = inzenden( array( 'email' => 'a@b.nl', 'name' => '' ), $alle, $ac );
klopt( 'checkbox met één optie leeg: Nee', $velden['nieuwsbrief']['value'], 'Nee' );
klopt( 'checkbox met één optie leeg: raw_value blijft leeg', $velden['nieuwsbrief']['raw_value'], '' );
klopt( 'acceptatie leeg: Nee', $velden['akkoord']['value'], 'Nee' );
klopt( 'acceptatie leeg: raw_value blijft leeg', $velden['akkoord']['raw_value'], '' );
klopt( 'checkbox met meer opties leeg: blijft leeg', $velden['interesses']['value'], '' );
klopt( 'radio leeg: blijft leeg', $velden['keuze']['value'], '' );
klopt( 'tekstveld leeg: blijft leeg', $velden['name']['value'], '' );

list( $na, $velden ) = inzenden( array( 'email' => 'a@b.nl', 'nieuwsbrief' => array( 'Ja' ), 'akkoord' => 'on', 'interesses' => array( 'Yoga' ) ), $alle, $beide );
klopt( 'checkbox met één optie aangevinkt: eigen waarde', $velden['nieuwsbrief']['value'], 'Ja' );
klopt( 'acceptatie aangevinkt: Ja', $velden['akkoord']['value'], 'Ja' );
klopt( 'acceptatie aangevinkt: raw_value blijft on', $velden['akkoord']['raw_value'], 'on' );
klopt( 'checkbox met meer opties: ongemoeid', $velden['interesses']['value'], 'Yoga' );
klopt( 'na Ja bij acceptatie draaien beide koppelingen', $na, $alle );

// Deel A kijkt naar raw_value: value Nee mag de koppeling niet laten draaien.
list( $na, $velden ) = inzenden( array( 'email' => 'a@b.nl' ), $alle, array( 'mm_mailerlite_vinkveld' => 'akkoord' ) );
klopt( 'acceptatie leeg met value Nee: MailerLite eruit', $na, array( 'save-to-database', 'email', 'activecampaign' ) );
klopt( 'acceptatie leeg met value Nee: value is Nee', $velden['akkoord']['value'], 'Nee' );

// Losse functies.
klopt( 'aantal opties: lege regels tellen niet', mm_nieuwsbrief_aantal_opties( "Ja\n\n  \n" ), 1 );
klopt( 'aantal opties: Windows-regeleinden', mm_nieuwsbrief_aantal_opties( "Een\r\nTwee" ), 2 );
klopt( 'aantal opties: geen opties', mm_nieuwsbrief_aantal_opties( '' ), 0 );
klopt( 'checkbox zonder opties: ongemoeid', mm_nieuwsbrief_ja_nee( array( 'x' => array( 'type' => 'checkbox', 'raw_value' => '' ) ), array( veld( 'x', 'checkbox', 'X' ) ) ), array() );
klopt( 'leeg: 0 als tekst is niet leeg', mm_nieuwsbrief_leeg( '0' ), false );
klopt( 'leeg: lege array', mm_nieuwsbrief_leeg( array() ), true );
klopt( 'beslissing zonder velden: onbekend', mm_nieuwsbrief_beslissing( null, 'x' ), 'onbekend' );
klopt( 'acties geen array: ongewijzigd', mm_nieuwsbrief_acties( 'email', array(), array() ), array( 'email', array() ) );

echo $fout ? "\n$fout fout(en)\n" : "\nAlles goed\n";
exit( $fout ? 1 : 0 );
