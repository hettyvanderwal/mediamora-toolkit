<?php
/**
 * Mediamora Toolkit, module: Tijdelijke accounts
 * Vervangt de mu-plugin mediamora-tijdelijke-accounts.php. Wordt alleen
 * geladen als de module aanstaat en die mu-plugin weg is.
 *
 * Accounts van externe partijen, bijvoorbeeld een marketeer die een pixel
 * plaatst, verlopen automatisch, zodat er geen beheerdersaccounts blijven
 * rondslingeren. Twee user meta, gelijk aan de mu-plugin:
 *
 * - mm_verloopt_op: timestamp. Vanaf dat moment wordt het account overal
 *   geweigerd.
 * - mm_overdragen_aan: user-ID dat de content krijgt. De dagelijkse cron
 *   mm_tijdelijke_accounts_opruimen verwijdert het verlopen account en draagt
 *   de content over. Zonder geldige erfgenaam blijft het account staan en
 *   alleen geblokkeerd.
 *
 * Dezelfde meta en dezelfde cronhook als de mu-plugin, dus zodra dat bestand
 * weg is neemt de module het over zonder dat er iets hoeft te veranderen. Een
 * event dat de mu-plugin heeft ingepland blijft gewoon staan.
 *
 * Valt bewust niet onder Onderhoudsklant: de module is ook voor klanten
 * zonder onderhoud. De velden zijn alleen voor Mediamora, met dezelfde regel
 * als het tabblad Mediamora.
 *
 * Multisite: wp_delete_user haalt het account daar alleen van deze site af.
 * Nieuwe gebruiker loopt daar buiten edit_user() om, dus de velden staan daar
 * alleen op het bewerkscherm.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_TIJDELIJK_VERLOOPT  = 'mm_verloopt_op';
const MM_TIJDELIJK_ERFGENAAM = 'mm_overdragen_aan';
const MM_TIJDELIJK_CRON      = 'mm_tijdelijke_accounts_opruimen';

/**
 * Terugval voor de mail op een site zonder beheerder van Mediamora.
 */
const MM_TIJDELIJK_ONTVANGER = 'hetty@mediamora.nl';


/* -------------------------------------------------------------------------
 * Blokkeren
 * ---------------------------------------------------------------------- */

/**
 * Is dit account verlopen? Geen, lege of ongeldige datum: nee.
 *
 * @param int $user_id
 * @return bool
 */
function mm_tijdelijk_verlopen( $user_id ) {
	$moment = (int) get_user_meta( (int) $user_id, MM_TIJDELIJK_VERLOOPT, true );
	return $moment > 0 && $moment <= time();
}

// Na de cookies en het applicatiewachtwoord, die draaien op 10 en 20. Zo geldt
// dit ook voor REST.
add_filter( 'determine_current_user', 'mm_tijdelijk_huidige_gebruiker', 99 );

function mm_tijdelijk_huidige_gebruiker( $user_id ) {
	return ( $user_id && mm_tijdelijk_verlopen( $user_id ) ) ? 0 : $user_id;
}

// wp_authenticate_user dekt inloggen met een wachtwoord. Een
// applicatiewachtwoord komt daar niet langs, en XML-RPC zet de gebruiker
// daarna zelf zonder determine_current_user. Daarom ook authenticate, na de
// inlogfilters van WordPress op 20.
add_filter( 'wp_authenticate_user', 'mm_tijdelijk_weiger_inloggen' );
add_filter( 'authenticate', 'mm_tijdelijk_weiger_inloggen', 99 );

function mm_tijdelijk_weiger_inloggen( $user ) {
	if ( $user instanceof WP_User && mm_tijdelijk_verlopen( $user->ID ) ) {
		return new WP_Error( 'mm_verlopen', 'Dit account is verlopen.' );
	}
	return $user;
}


/* -------------------------------------------------------------------------
 * Dagelijks opruimen
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mm_tijdelijk_inplannen' );
add_action( MM_TIJDELIJK_CRON, 'mm_tijdelijk_opruimen' );

function mm_tijdelijk_inplannen() {
	if ( ! wp_next_scheduled( MM_TIJDELIJK_CRON ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', MM_TIJDELIJK_CRON );
	}
}

/**
 * De erfgenaam van dit account, als die geldig is: ingevuld, bestaand en niet
 * het account zelf.
 *
 * @param int $user_id
 * @return WP_User|false
 */
function mm_tijdelijk_erfgenaam( $user_id ) {
	$erfgenaam = (int) get_user_meta( (int) $user_id, MM_TIJDELIJK_ERFGENAAM, true );
	if ( ! $erfgenaam || $erfgenaam === (int) $user_id ) {
		return false;
	}
	return get_user_by( 'id', $erfgenaam );
}

function mm_tijdelijk_opruimen() {

	$users = get_users(
		array(
			'meta_key'     => MM_TIJDELIJK_VERLOOPT,
			'meta_value'   => time(),
			'meta_compare' => '<=',
			'meta_type'    => 'NUMERIC',
		)
	);

	if ( ! $users ) {
		return;
	}

	// In de cron is dit bestand niet geladen.
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	$verwijderd = array();

	foreach ( $users as $user ) {

		// Een lege waarde telt in de query als 0 en komt dus ook hier langs.
		if ( ! mm_tijdelijk_verlopen( $user->ID ) ) {
			continue;
		}

		// Per account opnieuw kijken: twee verlopen accounts die naar elkaar
		// wijzen, mogen de content niet aan het net verwijderde account geven.
		$erfgenaam = mm_tijdelijk_erfgenaam( $user->ID );
		if ( ! $erfgenaam ) {
			continue; // Zonder geldige erfgenaam niet verwijderen, alleen geblokkeerd laten.
		}

		$moment = (int) get_user_meta( $user->ID, MM_TIJDELIJK_VERLOOPT, true );

		WP_Session_Tokens::get_instance( $user->ID )->destroy_all();

		if ( wp_delete_user( $user->ID, $erfgenaam->ID ) ) {
			$verwijderd[] = array(
				'account'   => mm_tijdelijk_label( $user ),
				'erfgenaam' => sprintf( '%s (%s)', $erfgenaam->display_name, $erfgenaam->user_login ),
				'moment'    => $moment,
			);
		}
	}

	if ( $verwijderd && mm_tijdelijk_mail_aan() ) {
		mm_tijdelijk_mail( $verwijderd );
	}
}


/* -------------------------------------------------------------------------
 * Mail
 *
 * Eén mail per ronde, zodat de lijst met beheerders ook echt de stand is na
 * het opruimen. Daaraan is te zien of er onderweg een beheerder bij is
 * gekomen. Daarom telt het recht manage_options en niet de rol: ook een
 * eigen rol met dat recht moet erin staan.
 * ---------------------------------------------------------------------- */

function mm_tijdelijk_mail_aan() {
	return (bool) get_option( MM_TOOLKIT_TIJDELIJK_MAIL_OPTIE, 1 );
}

function mm_tijdelijk_label( $user ) {
	return sprintf( '%s (%s, %s)', $user->display_name, $user->user_login, $user->user_email );
}

function mm_tijdelijk_beheerders() {
	return get_users( array( 'capability' => 'manage_options' ) );
}

/**
 * De beheerders van Mediamora op deze site. In de cron is niemand ingelogd,
 * dus mm_toolkit_mediamora_gebruiker() helpt hier niet.
 *
 * @return string[]
 */
function mm_tijdelijk_mail_ontvangers() {
	$adressen = array();
	foreach ( mm_tijdelijk_beheerders() as $user ) {
		if ( mm_toolkit_mediamora_adres( $user->user_email ) ) {
			$adressen[] = $user->user_email;
		}
	}
	return $adressen ? $adressen : array( MM_TIJDELIJK_ONTVANGER );
}

/**
 * @param array[] $verwijderd Per verwijderd account: account, erfgenaam, moment.
 */
function mm_tijdelijk_mail( $verwijderd ) {

	$host   = wp_parse_url( home_url(), PHP_URL_HOST );
	$domein = preg_replace( '/^www\./i', '', is_string( $host ) ? $host : 'onbekend' );

	$body   = array();
	$body[] = sprintf( 'Site: %s', $domein );
	$body[] = '';

	foreach ( $verwijderd as $regel ) {
		$body[] = sprintf( 'Verwijderd: %s', $regel['account'] );
		$body[] = sprintf( 'Verlopen op: %s', wp_date( 'j F Y', $regel['moment'] ) );
		$body[] = sprintf( 'Content overgedragen aan: %s', $regel['erfgenaam'] );
		$body[] = '';
	}

	$body[] = 'Beheerders op deze site na het opruimen:';
	foreach ( mm_tijdelijk_beheerders() as $user ) {
		$body[] = '- ' . mm_tijdelijk_label( $user );
	}

	wp_mail( mm_tijdelijk_mail_ontvangers(), sprintf( '[Tijdelijke accounts] %s', $domein ), implode( "\n", $body ) );
}


/* -------------------------------------------------------------------------
 * Velden op het profiel en bij Nieuwe gebruiker
 *
 * Alleen voor Mediamora, met dezelfde regel als het tabblad. Een beheerder
 * van de klant krijgt de velden en de nonce niet en kan dus ook niets wissen
 * door een account op te slaan.
 *
 * Volgorde bij opslaan in WordPress: user_profile_update_errors kijkt mee met
 * het nieuwe e-mailadres en kan het opslaan tegenhouden. Daarna is het account
 * bijgewerkt en komt profile_update, of edit_user_created_user bij een nieuw
 * account. personal_options_update en edit_user_profile_update zijn te vroeg:
 * die draaien voor de controle en zien het oude adres nog.
 * ---------------------------------------------------------------------- */

add_action( 'show_user_profile', 'mm_tijdelijk_velden' );
add_action( 'edit_user_profile', 'mm_tijdelijk_velden' );
add_action( 'user_new_form', 'mm_tijdelijk_velden' );

add_action( 'user_profile_update_errors', 'mm_tijdelijk_controleren', 10, 3 );
add_action( 'profile_update', 'mm_tijdelijk_opslaan' );
add_action( 'edit_user_created_user', 'mm_tijdelijk_nieuwe_gebruiker' );

function mm_tijdelijk_mag_instellen() {
	return current_user_can( 'manage_options' ) && mm_toolkit_mediamora_gebruiker();
}

/**
 * @param WP_User|string $user De gebruiker, of bij user_new_form de soort formulier.
 */
function mm_tijdelijk_velden( $user ) {

	$bestaand = $user instanceof WP_User;

	if ( ! mm_tijdelijk_mag_instellen() || ( ! $bestaand && is_multisite() ) ) {
		return;
	}

	$id = $bestaand ? (int) $user->ID : 0;

	echo '<h2>Tijdelijk account</h2>';
	wp_nonce_field( 'mm_tijdelijk_' . $id, 'mm_tijdelijk_nonce', false );

	if ( $bestaand && mm_toolkit_mediamora_adres( $user->user_email ) ) {
		echo '<p>Accounts van Mediamora krijgen geen verloopdatum.</p>';
		return;
	}

	// Na een foutmelding bij Nieuwe gebruiker staat de keuze er nog, anders
	// komt er bij de volgende poging stil een account zonder verloopdatum.
	if ( $bestaand ) {
		$moment  = (int) get_user_meta( $id, MM_TIJDELIJK_VERLOOPT, true );
		$datum   = $moment ? wp_date( 'Y-m-d', $moment ) : '';
		$gekozen = (int) get_user_meta( $id, MM_TIJDELIJK_ERFGENAAM, true );
	} else {
		$datum   = mm_tijdelijk_gepost( MM_TIJDELIJK_VERLOOPT );
		$gekozen = absint( mm_tijdelijk_gepost( MM_TIJDELIJK_ERFGENAAM ) );
	}

	echo '<table class="form-table" role="presentation">';

	echo '<tr><th><label for="' . esc_attr( MM_TIJDELIJK_VERLOOPT ) . '">Verloopt op</label></th><td>';
	echo '<input type="date" name="' . esc_attr( MM_TIJDELIJK_VERLOOPT ) . '" id="' . esc_attr( MM_TIJDELIJK_VERLOOPT ) . '" value="' . esc_attr( $datum ) . '">';
	echo '<p class="description">Verloopt om 23:59:59 in de tijdzone van de site. Leeg: verloopt niet.</p>';
	echo '</td></tr>';

	echo '<tr><th><label for="' . esc_attr( MM_TIJDELIJK_ERFGENAAM ) . '">Content overdragen aan</label></th><td>';
	// Alleen gebruikers die content kunnen maken: op een webshop of academie
	// zouden anders alle klanten in de lijst komen. include_selected houdt een
	// eerder gekozen gebruiker er wel in.
	echo wp_dropdown_users(
		array(
			'name'              => MM_TIJDELIJK_ERFGENAAM,
			'id'                => MM_TIJDELIJK_ERFGENAAM,
			'selected'          => $gekozen === $id ? 0 : $gekozen,
			'exclude'           => array( $id ),
			'capability'        => 'edit_posts',
			'include_selected'  => true,
			'show'              => 'display_name_with_login',
			'show_option_none'  => 'Niet overdragen',
			'option_none_value' => 0,
			'echo'              => false,
		)
	);
	echo '<p class="description">Zonder keuze wordt het account na de verloopdatum wel geblokkeerd, maar niet verwijderd.</p>';
	echo '</td></tr>';

	echo '</table>';
}

function mm_tijdelijk_gepost( $veld ) {
	return isset( $_POST[ $veld ] ) ? sanitize_text_field( wp_unslash( $_POST[ $veld ] ) ) : '';
}

/**
 * Komt dit bericht van onze velden, en mag deze gebruiker ze opslaan?
 *
 * @param int $user_id 0 bij Nieuwe gebruiker. Hoort bij de nonce, zodat een
 *                     wp_update_user op een ander account in hetzelfde
 *                     verzoek niets met de velden doet.
 * @return bool
 */
function mm_tijdelijk_formulier_geldig( $user_id ) {
	if ( ! mm_tijdelijk_mag_instellen() || ! current_user_can( $user_id ? 'edit_user' : 'create_users', $user_id ) ) {
		return false;
	}
	return isset( $_POST['mm_tijdelijk_nonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mm_tijdelijk_nonce'] ) ), 'mm_tijdelijk_' . $user_id );
}

/**
 * Datum uit het veld als timestamp, om 23:59:59 in de tijdzone van de site.
 *
 * @param string $datum JJJJ-MM-DD.
 * @return int 0 als de datum niet bestaat.
 */
function mm_tijdelijk_datum_naar_moment( $datum ) {
	$moment = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $datum, wp_timezone() );
	if ( ! $moment || $moment->format( 'Y-m-d' ) !== $datum ) {
		return 0;
	}
	return $moment->setTime( 23, 59, 59 )->getTimestamp();
}

/**
 * @param WP_Error $fouten
 * @param bool     $update False bij Nieuwe gebruiker.
 * @param object   $user   De gegevens zoals ze opgeslagen gaan worden.
 */
function mm_tijdelijk_controleren( $fouten, $update, $user ) {

	$id = $update ? (int) $user->ID : 0;

	if ( ! mm_tijdelijk_formulier_geldig( $id ) ) {
		return;
	}

	$datum = mm_tijdelijk_gepost( MM_TIJDELIJK_VERLOOPT );

	if ( '' === $datum ) {
		return;
	}

	if ( isset( $user->user_email ) && mm_toolkit_mediamora_adres( $user->user_email ) ) {
		$fouten->add( 'mm_tijdelijk_mediamora', 'Accounts van Mediamora krijgen geen verloopdatum.' );
		return;
	}

	if ( ! mm_tijdelijk_datum_naar_moment( $datum ) ) {
		$fouten->add( 'mm_tijdelijk_datum', 'Deze verloopdatum bestaat niet.' );
	}

	$erfgenaam = absint( mm_tijdelijk_gepost( MM_TIJDELIJK_ERFGENAAM ) );

	if ( $erfgenaam && ( $erfgenaam === $id || ! get_user_by( 'id', $erfgenaam ) ) ) {
		$fouten->add( 'mm_tijdelijk_erfgenaam', 'Kies voor de overdracht een andere gebruiker.' );
	}
}

function mm_tijdelijk_opslaan( $user_id ) {
	if ( mm_tijdelijk_formulier_geldig( (int) $user_id ) ) {
		mm_tijdelijk_bewaar( (int) $user_id );
	}
}

function mm_tijdelijk_nieuwe_gebruiker( $user_id ) {
	if ( mm_tijdelijk_formulier_geldig( 0 ) ) {
		mm_tijdelijk_bewaar( (int) $user_id );
	}
}

/**
 * Zonder datum geen meta, zodat er geen waarden blijven hangen die
 * mm_tijdelijk_opruimen weer moet overslaan. Een account dat nu een adres van
 * Mediamora heeft, verliest een eventuele oude datum.
 *
 * @param int $user_id
 */
function mm_tijdelijk_bewaar( $user_id ) {

	$user      = get_user_by( 'id', $user_id );
	$moment    = mm_tijdelijk_datum_naar_moment( mm_tijdelijk_gepost( MM_TIJDELIJK_VERLOOPT ) );
	$erfgenaam = absint( mm_tijdelijk_gepost( MM_TIJDELIJK_ERFGENAAM ) );

	if ( ! $user || ! $moment || mm_toolkit_mediamora_adres( $user->user_email ) ) {
		delete_user_meta( $user_id, MM_TIJDELIJK_VERLOOPT );
		delete_user_meta( $user_id, MM_TIJDELIJK_ERFGENAAM );
		return;
	}

	update_user_meta( $user_id, MM_TIJDELIJK_VERLOOPT, $moment );

	if ( $erfgenaam && $erfgenaam !== $user_id && get_user_by( 'id', $erfgenaam ) ) {
		update_user_meta( $user_id, MM_TIJDELIJK_ERFGENAAM, $erfgenaam );
	} else {
		delete_user_meta( $user_id, MM_TIJDELIJK_ERFGENAAM );
	}
}


/* -------------------------------------------------------------------------
 * Overzicht op het tabblad Mediamora
 *
 * mm_toolkit_scherm() roept dit aan als de module laadt.
 * ---------------------------------------------------------------------- */

function mm_tijdelijk_overzicht() {

	echo '<h2 id="mm-tijdelijke-accounts" style="margin-top:32px;">Tijdelijke accounts</h2>';

	$users = get_users(
		array(
			'meta_key'     => MM_TIJDELIJK_VERLOOPT,
			'meta_value'   => 0,
			'meta_compare' => '>',
			'meta_type'    => 'NUMERIC',
			'orderby'      => 'meta_value_num',
			'order'        => 'ASC',
		)
	);

	if ( ! $users ) {
		echo '<p>Geen accounts met een verloopdatum.</p>';
		return;
	}

	$rollen = wp_roles()->get_names();

	echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Naam</th><th>Rol</th><th>Verloopt op</th><th>Overdragen aan</th></tr></thead><tbody>';

	foreach ( $users as $user ) {

		$namen = array();
		foreach ( $user->roles as $rol ) {
			$namen[] = isset( $rollen[ $rol ] ) ? translate_user_role( $rollen[ $rol ] ) : $rol;
		}

		$verloopt = esc_html( wp_date( 'j F Y', (int) get_user_meta( $user->ID, MM_TIJDELIJK_VERLOOPT, true ) ) );
		if ( mm_tijdelijk_verlopen( $user->ID ) ) {
			$verloopt .= ' <span style="color:#646970;">(geblokkeerd)</span>';
		}

		$erfgenaam = mm_tijdelijk_erfgenaam( $user->ID );

		echo '<tr>';
		echo '<td><a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a> <span style="color:#646970;">' . esc_html( $user->user_login ) . '</span></td>';
		echo '<td>' . esc_html( implode( ', ', $namen ) ) . '</td>';
		echo '<td>' . $verloopt . '</td>';
		echo '<td>' . ( $erfgenaam ? esc_html( $erfgenaam->display_name ) : '<span style="color:#b32d2e;">Geen geldige erfgenaam: wordt niet verwijderd</span>' ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}
