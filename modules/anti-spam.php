<?php
/**
 * Mediamora Toolkit, module: Anti-spam voor Elementor Forms
 * Overgenomen uit mediamora-anti-spam-elementor 5.4. De uitgebreide versiegeschiedenis staat in die repo. Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =====================================================================
// PADEN - afgeleide bestandslocaties (geen "instelling", niet in het
// instellingenscherm, want dit zijn technische implementatiedetails)
//
// De mapnaam krijgt een willekeurig achtervoegsel dat per site eenmalig
// wordt aangemaakt en in de optie mediamora_antispam_log_dir staat. De
// logbestanden bevatten formulierinzendingen, dus persoonsgegevens, en op
// hosting waar .htaccess niets doet (Nginx) is een onraadbare mapnaam de
// enige drempel die overblijft. Geen echte afscherming, wel het verschil
// tussen "op te vragen" en "op te vragen als je het pad al kent".
//
// Daarom functies en geen constanten: de naam staat in de database en is
// dus pas bekend op het moment dat er iets gelogd wordt.
// =====================================================================

/**
 * De logmap van deze site. Absoluut pad, zonder slash aan het eind.
 *
 * @return string
 */
function mediamora_antispam_log_dir() {

	static $dir = null;

	if ( null !== $dir ) {
		return $dir;
	}

	$naam = get_option( 'mediamora_antispam_log_dir', '' );

	// Strak valideren: deze waarde gaat rechtstreeks in een bestandspad.
	// Ontbreekt hij of is hij onherkenbaar, dan maken we een nieuwe aan.
	if ( ! is_string( $naam ) || ! preg_match( '/^mediamora-antispam-[a-z0-9]{8,32}$/', $naam ) ) {
		$naam = 'mediamora-antispam-' . mediamora_antispam_random_suffix();
		update_option( 'mediamora_antispam_log_dir', $naam, false );
	}

	$dir = WP_CONTENT_DIR . '/uploads/' . $naam;

	return $dir;
}

/**
 * De map zoals die tot en met 1.1.0 heette: vast, voor iedereen gelijk en
 * dus raadbaar. Alleen nog in gebruik om ervanaf te verhuizen.
 *
 * @return string
 */
function mediamora_antispam_legacy_log_dir() {
	return WP_CONTENT_DIR . '/uploads/mediamora-antispam';
}

/**
 * Twaalf willekeurige tekens voor achter de mapnaam.
 *
 * @return string
 */
function mediamora_antispam_random_suffix() {

	if ( function_exists( 'wp_generate_password' ) ) {
		return strtolower( wp_generate_password( 12, false, false ) );
	}

	return substr( md5( uniqid( '', true ) ), 0, 12 );
}

/**
 * @return string
 */
function mediamora_antispam_log_file() {
	return mediamora_antispam_log_dir() . '/log.txt';
}

/**
 * @return string
 */
function mediamora_antispam_nearmiss_file() {
	return mediamora_antispam_log_dir() . '/near-miss.txt';
}

/**
 * @return string
 */
function mediamora_antispam_debug_file() {
	return mediamora_antispam_log_dir() . '/debug.txt';
}

/**
 * Het logpad zoals het in een mail of op het instellingenscherm wordt
 * getoond: vanaf wp-content, want het volledige serverpad zegt niets extra.
 *
 * @param string $bestand
 * @return string
 */
function mediamora_antispam_log_path_label( $bestand ) {
	return str_replace( WP_CONTENT_DIR . '/', 'wp-content/', $bestand );
}

// =====================================================================
// INSTELLINGEN - opgeslagen in de database (get_option/update_option),
// bewerkbaar via wp-admin > Instellingen > Mediamora Anti-Spam
// =====================================================================

/**
 * Standaardwaardes. Worden gebruikt zolang er nog niets is opgeslagen in
 * de database, en gelden ook als een toekomstige code-update een nieuwe
 * instelling toevoegt die nog niet in de opgeslagen database-waarde zit.
 *
 * @return array
 */
function mediamora_antispam_default_settings() {
	return array(
		// Rapportage
		'alert_email'             => 'hetty@mediamora.nl',
		'report_enabled'          => true,
		'email_interval'          => DAY_IN_SECONDS,
		'log_max_age'             => 30 * DAY_IN_SECONDS,

		// Modus
		'debug'                   => false,
		'reject_non_latin_text'   => true,
		'reject_own_domain_mention' => true,
		'reject_shortened_urls'    => true,
		'reject_scheduling_links'  => true,
		'reject_name_underscore'   => true,
		'reject_name_digits'       => true,
		'reject_name_domain'       => true,
		'reject_textarea_only_url' => true,
		'check_postcode'           => true,

		// Vangnet voor mogelijke vals-negatieven
		'nearmiss_alert_enabled'  => true,
		'nearmiss_email_interval' => DAY_IN_SECONDS,

		// Gibberish-detectie (automatisch gegenereerde tekst)
		'gibberish_min_length'       => 8,
		'gibberish_vowel_ratio'      => 0.25,
		'gibberish_transitions'      => 4,
		'gibberish_structure_length' => 12,
		'gibberish_threshold'        => 2,

		// Link-detectie
		'max_links_textarea' => 2,
		'max_links_text'     => 1,

		// Duplicate-content detectie
		'duplicate_min_length' => 50,

		// Niet-Latijns schrift detectie
		'non_latin_min_letters' => 15,
		'non_latin_ratio'       => 0.5,
		'non_latin_min_count'   => 3,

		// Herhaling-detectie (keyword stuffing)
		'repetition_min_words'       => 10,
		'repetition_min_word_length' => 4,
		'repetition_min_count'       => 4,
		'repetition_max_ratio'       => 0.15,

		// Velden per formulier die buiten alle checks vallen:
		// formulier-ID => lijst veld-ID's, of array( '*' ) voor alle velden.
		'exempt_fields' => array(),
	);
}

/**
 * Leest de uitgezonderde velden uit het tekstveld op het instellingenscherm.
 * Per regel één formulier: "formulier-ID: veld-ID, veld-ID", of
 * "formulier-ID: *" voor alle velden van dat formulier. Lege regels en
 * spaties tellen niet. Staat een formulier op meer regels, dan tellen de
 * velden van alle regels samen.
 *
 * Een ongeldige regel wordt niet opgeslagen en komt in $errors. De rest
 * wel: anders gaat er een hele lijst verloren om één tikfout.
 *
 * @param string   $text
 * @param string[] $errors Hier komen de meldingen bij.
 * @return array formulier-ID => lijst veld-ID's.
 */
function mediamora_antispam_parse_exempt_fields( $text, &$errors = array() ) {

	$map = array();

	foreach ( preg_split( '/\R/', (string) $text ) as $nummer => $regel ) {

		$regel = trim( $regel );
		if ( '' === $regel ) {
			continue;
		}

		$delen   = explode( ':', $regel, 2 );
		$form_id = trim( $delen[0] );
		$velden  = isset( $delen[1] ) ? preg_split( '/\s*,\s*/', trim( $delen[1] ), -1, PREG_SPLIT_NO_EMPTY ) : array();
		$fout    = '';

		if ( ! isset( $delen[1] ) ) {
			$fout = 'er staat geen dubbele punt tussen het formulier-ID en de velden';
		} elseif ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $form_id ) ) {
			$fout = '' === $form_id ? 'het formulier-ID ontbreekt' : sprintf( '"%s" is geen geldig formulier-ID', $form_id );
		} elseif ( empty( $velden ) ) {
			$fout = 'er staan geen veld-ID\'s achter de dubbele punt (gebruik * voor alle velden)';
		} else {
			foreach ( $velden as $veld ) {
				if ( '*' !== $veld && ! preg_match( '/^[A-Za-z0-9_-]+$/', $veld ) ) {
					$fout = sprintf( '"%s" is geen geldig veld-ID', $veld );
					break;
				}
			}
		}

		if ( '' !== $fout ) {
			$errors[] = sprintf( 'Uitgezonderde velden, regel %d ("%s"): %s. Deze regel is niet opgeslagen.', $nummer + 1, $regel, $fout );
			continue;
		}

		$velden = in_array( '*', $velden, true ) || ( isset( $map[ $form_id ] ) && array( '*' ) === $map[ $form_id ] )
			? array( '*' )
			: array_values( array_unique( array_merge( isset( $map[ $form_id ] ) ? $map[ $form_id ] : array(), $velden ) ) );

		$map[ $form_id ] = $velden;
	}

	return $map;
}

/**
 * Zet de opgeslagen uitgezonderde velden terug in de vorm van het tekstveld.
 *
 * @param array $map formulier-ID => lijst veld-ID's.
 * @return string
 */
function mediamora_antispam_format_exempt_fields( $map ) {

	$regels = array();

	foreach ( (array) $map as $form_id => $velden ) {
		$regels[] = $form_id . ': ' . implode( ', ', (array) $velden );
	}

	return implode( "\n", $regels );
}

/**
 * Valt dit veld van dit formulier buiten de checks?
 *
 * @param array  $map     formulier-ID => lijst veld-ID's.
 * @param string $form_id Het Elementor element-ID van de formulierwidget.
 * @param string $field_id De custom_id van het veld.
 * @return bool
 */
function mediamora_antispam_is_exempt_field( $map, $form_id, $field_id ) {

	$form_id = (string) $form_id;

	if ( ! is_array( $map ) || '' === $form_id || empty( $map[ $form_id ] ) || ! is_array( $map[ $form_id ] ) ) {
		return false;
	}

	return in_array( '*', $map[ $form_id ], true ) || in_array( (string) $field_id, $map[ $form_id ], true );
}

/**
 * Haalt de huidige instellingen op (database, aangevuld met standaardwaardes
 * voor eventueel nog ontbrekende sleutels). Gecachet per pageload.
 *
 * @return array
 */
function mediamora_antispam_settings() {

	static $settings = null;

	if ( null === $settings ) {
		$stored   = get_option( 'mediamora_antispam_settings', array() );
		$settings = wp_parse_args( $stored, mediamora_antispam_default_settings() );
	}

	return $settings;
}

// =====================================================================
// INSTELLINGENSCHERM (wp-admin > Instellingen > Mediamora Anti-Spam)
// =====================================================================

add_action( 'admin_menu', 'mediamora_antispam_add_settings_page' );

function mediamora_antispam_add_settings_page() {
	add_options_page(
		'Mediamora Anti-Spam',
		'Mediamora Anti-Spam',
		'manage_options',
		'mediamora-antispam',
		'mediamora_antispam_render_settings_page'
	);
}

/**
 * Valideert en bewaart de ingestuurde instellingen. Retourneert de
 * opgeslagen waardes (met standaardwaardes aangevuld), zodat de pagina
 * de nieuwe waardes direct kan tonen.
 *
 * Een ongeldig of leeg meldingsadres wordt niet opgeslagen: dan blijft het
 * vorige adres staan en komt er een foutmelding in $errors. Anders zou
 * sanitize_email() er een lege string van maken, die stil wordt bewaard,
 * waarna er geen enkele mail meer aankomt. Leeg is hier nooit bedoeld, het
 * veld is verplicht.
 *
 * @param array $post
 * @param array $errors Foutmeldingen voor het instellingenscherm.
 * @return array
 */
function mediamora_antispam_sanitize_and_save_settings( $post, &$errors = array() ) {

	$defaults = mediamora_antispam_default_settings();
	$current  = mediamora_antispam_settings();
	$clean    = array();
	$errors   = array();

	$clean['alert_email'] = $current['alert_email'];
	if ( isset( $post['alert_email'] ) ) {
		$entered = trim( (string) $post['alert_email'] );
		$email   = sanitize_email( $entered );
		if ( is_email( $email ) ) {
			$clean['alert_email'] = $email;
		} else {
			$errors[] = sprintf(
				'%s Het vorige adres (%s) is blijven staan.',
				'' === $entered ? 'Het e-mailadres voor meldingen mag niet leeg zijn.' : sprintf( '"%s" is geen geldig e-mailadres.', $entered ),
				'' !== $current['alert_email'] ? $current['alert_email'] : 'leeg'
			);
		}
	}
	$clean['report_enabled'] = ! empty( $post['report_enabled'] );
	$clean['email_interval'] = max( 1, (int) ( $post['email_interval_hours'] ?? 24 ) ) * HOUR_IN_SECONDS;
	$clean['log_max_age']    = max( 1, (int) ( $post['log_max_age_days'] ?? 30 ) ) * DAY_IN_SECONDS;

	$clean['debug']                    = ! empty( $post['debug'] );
	$clean['reject_non_latin_text']    = ! empty( $post['reject_non_latin_text'] );
	$clean['reject_own_domain_mention'] = ! empty( $post['reject_own_domain_mention'] );
	$clean['reject_shortened_urls']    = ! empty( $post['reject_shortened_urls'] );
	$clean['reject_scheduling_links']  = ! empty( $post['reject_scheduling_links'] );
	$clean['reject_name_underscore']   = ! empty( $post['reject_name_underscore'] );
	$clean['reject_name_digits']       = ! empty( $post['reject_name_digits'] );
	$clean['reject_name_domain']       = ! empty( $post['reject_name_domain'] );
	$clean['reject_textarea_only_url'] = ! empty( $post['reject_textarea_only_url'] );
	$clean['check_postcode']           = ! empty( $post['check_postcode'] );

	$clean['nearmiss_alert_enabled']  = ! empty( $post['nearmiss_alert_enabled'] );
	$clean['nearmiss_email_interval'] = max( 1, (int) ( $post['nearmiss_email_interval_hours'] ?? 24 ) ) * HOUR_IN_SECONDS;

	$clean['gibberish_min_length']       = max( 1, (int) ( $post['gibberish_min_length'] ?? 8 ) );
	$clean['gibberish_vowel_ratio']      = min( 1, max( 0, (float) ( $post['gibberish_vowel_ratio'] ?? 0.25 ) ) );
	$clean['gibberish_transitions']      = max( 1, (int) ( $post['gibberish_transitions'] ?? 4 ) );
	$clean['gibberish_structure_length'] = max( 1, (int) ( $post['gibberish_structure_length'] ?? 12 ) );
	$clean['gibberish_threshold']        = max( 1, min( 3, (int) ( $post['gibberish_threshold'] ?? 2 ) ) );

	$clean['max_links_textarea'] = max( 0, (int) ( $post['max_links_textarea'] ?? 2 ) );
	$clean['max_links_text']     = max( 0, (int) ( $post['max_links_text'] ?? 1 ) );

	$clean['duplicate_min_length'] = max( 1, (int) ( $post['duplicate_min_length'] ?? 50 ) );

	$clean['non_latin_min_letters'] = max( 1, (int) ( $post['non_latin_min_letters'] ?? 15 ) );
	$clean['non_latin_ratio']       = min( 1, max( 0, (float) ( $post['non_latin_ratio'] ?? 0.5 ) ) );
	$clean['non_latin_min_count']   = max( 1, (int) ( $post['non_latin_min_count'] ?? 3 ) );

	$clean['repetition_min_words']       = max( 1, (int) ( $post['repetition_min_words'] ?? 10 ) );
	$clean['repetition_min_word_length'] = max( 1, (int) ( $post['repetition_min_word_length'] ?? 4 ) );
	$clean['repetition_min_count']       = max( 1, (int) ( $post['repetition_min_count'] ?? 4 ) );
	$clean['repetition_max_ratio']       = min( 1, max( 0, (float) ( $post['repetition_max_ratio'] ?? 0.15 ) ) );

	$clean['exempt_fields'] = isset( $post['exempt_fields'] )
		? mediamora_antispam_parse_exempt_fields( $post['exempt_fields'], $errors )
		: $current['exempt_fields'];

	update_option( 'mediamora_antispam_settings', $clean, false );

	// Rapportmail of vangnet weer aangezet: die begint bij nu. Een mail bevat
	// alles sinds de vorige run, en zonder dit zou de eerste mail na het
	// aanzetten alles inhalen wat er in de tussentijd gelogd is. Voor het
	// vangnet kan dat weken zijn, want dat draait niet als het uit staat.
	if ( $clean['report_enabled'] && ! $current['report_enabled'] ) {
		update_option( 'mediamora_antispam_last_report', time(), false );
	}
	if ( $clean['nearmiss_alert_enabled'] && ! $current['nearmiss_alert_enabled'] ) {
		update_option( 'mediamora_antispam_last_nearmiss_alert', time(), false );
	}

	// Debug staat uit: dan hoort debug.txt er ook niet meer te liggen. Dat bestand
	// legt elk veld van elke inzending vast, ook van berichten die gewoon zijn
	// doorgelaten, en er wordt verder niets mee gedaan. Blijft het staan, dan bewaar
	// je persoonsgegevens van bezoekers zonder dat daar nog een doel voor is.
	//
	// Bewust bij elke opslag met debug uit, niet alleen bij het omzetten van aan naar
	// uit: zo verdwijnt ook een bestand dat is blijven liggen van voor deze versie.
	if ( ! $clean['debug'] ) {
		$debug_file = mediamora_antispam_debug_file();
		if ( file_exists( $debug_file ) ) {
			@unlink( $debug_file );
		}
	}

	return wp_parse_args( $clean, $defaults );
}

function mediamora_antispam_render_settings_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved  = false;
	$errors = array();

	if ( isset( $_POST['mediamora_antispam_save'] ) && check_admin_referer( 'mediamora_antispam_save_settings' ) ) {
		$s     = mediamora_antispam_sanitize_and_save_settings( wp_unslash( $_POST ), $errors );
		$saved = true;
	} else {
		$s = mediamora_antispam_settings();
	}
	?>
	<div class="wrap">
		<h1>Mediamora Anti-Spam voor Elementor Forms</h1>

		<?php foreach ( $errors as $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo $errors ? 'De overige instellingen zijn opgeslagen.' : 'Instellingen opgeslagen.'; ?></p></div>
		<?php endif; ?>

		<p>Deze instellingen staan in de database van deze site en blijven dus behouden als het plugin-bestand zelf een keer wordt vervangen door een nieuwere versie.</p>

		<p>De logbestanden staan in <code><?php echo esc_html( mediamora_antispam_log_path_label( mediamora_antispam_log_dir() ) ); ?></code>. Die mapnaam eindigt op een reeks willekeurige tekens die per site verschilt, zodat het logbestand niet via een raadbaar adres op te halen is op hosting waar <code>.htaccess</code> niets doet.</p>

		<form method="post">
			<?php wp_nonce_field( 'mediamora_antispam_save_settings' ); ?>

			<h2>Rapportage</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="alert_email">E-mailadres voor meldingen</label></th>
					<td><input type="email" id="alert_email" name="alert_email" class="regular-text" value="<?php echo esc_attr( $s['alert_email'] ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row">Dagelijkse rapportmail</th>
					<td>
						<label><input type="checkbox" name="report_enabled" value="1" <?php checked( $s['report_enabled'] ); ?>> Stuur een samenvattend rapport van geweigerde inzendingen</label>
						<p class="description">Zet dit per site uit zodra je vertrouwt dat de check hier stabiel draait. Het logbestand op de server blijft altijd bijgehouden, ook als dit uit staat, en toont nu alle geweigerde inzendingen zonder maximum.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="email_interval_hours">Minimale tijd tussen rapportmails</label></th>
					<td><input type="number" min="1" id="email_interval_hours" name="email_interval_hours" value="<?php echo esc_attr( round( $s['email_interval'] / HOUR_IN_SECONDS ) ); ?>" class="small-text"> uur</td>
				</tr>
				<tr>
					<th scope="row"><label for="log_max_age_days">Bewaartermijn logregels</label></th>
					<td><input type="number" min="1" id="log_max_age_days" name="log_max_age_days" value="<?php echo esc_attr( round( $s['log_max_age'] / DAY_IN_SECONDS ) ); ?>" class="small-text"> dagen</td>
				</tr>
			</table>

			<h2>Vangnet voor mogelijk gemiste spam</h2>
			<p class="description">Waarschuwt bij individuele VELDEN die net niet werden geweigerd, maar verdacht dicht bij een drempel zaten. De mail geeft ook aan of een ander veld door ONZE checks alsnog werd geweigerd. Let op: als dat niet zo is, betekent dat niet automatisch dat de inzending ook echt in je Elementor Inzendingen-lijst staat, Elementor Pro heeft een eigen honeypot-bescherming die hier los van kan ingrijpen. Dit is sowieso een heuristiek: het vangt twijfelgevallen, geen garantie op elke gemiste spamvorm. Draait onafhankelijk van de rapportmail hierboven.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Waarschuwing bij twijfelgevallen</th>
					<td><label><input type="checkbox" name="nearmiss_alert_enabled" value="1" <?php checked( $s['nearmiss_alert_enabled'] ); ?>> Waarschuw mij bij mogelijke twijfelgevallen</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="nearmiss_email_interval_hours">Minimale tijd tussen vangnet-mails</label></th>
					<td><input type="number" min="1" id="nearmiss_email_interval_hours" name="nearmiss_email_interval_hours" value="<?php echo esc_attr( round( $s['nearmiss_email_interval'] / HOUR_IN_SECONDS ) ); ?>" class="small-text"> uur</td>
				</tr>
			</table>

			<h2>Modus</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Niet-Latijns schrift altijd weigeren</th>
					<td>
						<label><input type="checkbox" name="reject_non_latin_text" value="1" <?php checked( $s['reject_non_latin_text'] ); ?>> Weiger overwegend niet-Latijnse tekst (Cyrillisch, Arabisch, Chinees, etc.), ook zonder link</label>
						<p class="description">Alleen uitzetten bij een site met legitiem internationaal publiek buiten het Latijnse schrift.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Debug-modus</th>
					<td>
						<label><input type="checkbox" name="debug" value="1" <?php checked( $s['debug'] ); ?>> Log elk veld van elke inzending naar debug.txt, ongeacht of het geweigerd wordt</label>
						<p class="description">Alleen tijdelijk aanzetten om te troubleshooten, hierna weer uitzetten. Zodra je hem uitzet en opslaat wordt debug.txt verwijderd, en zolang hij aan staat geldt dezelfde bewaartermijn als voor de andere logbestanden.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Eigen domeinnaam in bericht</th>
					<td>
						<label><input type="checkbox" name="reject_own_domain_mention" value="1" <?php checked( $s['reject_own_domain_mention'] ); ?>> Weiger berichten die de domeinnaam van deze site zelf noemen</label>
						<p class="description">Vangt bulk-outreach/verkooppitches die met een sjabloon-tool naar veel sites tegelijk worden gestuurd, met de domeinnaam als variabele erin ("Ik zag net [domeinnaam].nl en..."). Een echte bezoeker heeft geen reden om de naam van de site waar hij al op staat te typen.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Verkorte URLs &amp; wegwerp-linkdiensten</th>
					<td>
						<label><input type="checkbox" name="reject_shortened_urls" value="1" <?php checked( $s['reject_shortened_urls'] ); ?>> Weiger berichten met verkorte URLs of bekende wegwerp-linkdiensten (bit.ly, tinyurl, psee.io, telegra.ph, enz.)</label>
						<p class="description">Werkt op een vaste lijst van bekende diensten, die op naam herkend worden. Onbekende of nieuwe verkorters glippen er dus doorheen: dat is sinds 5.4 een bewuste keuze, omdat de vorige structurele herkenning te veel gewone links raakte (een pagina als "/Contact" of "/2024" werd dan ook geweigerd). Kom je een verkorter tegen die er telkens doorheen komt, geef de domeinnaam dan door zodat hij aan de lijst kan.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Afspraken-boekingslinks</th>
					<td>
						<label><input type="checkbox" name="reject_scheduling_links" value="1" <?php checked( $s['reject_scheduling_links'] ); ?>> Weiger berichten met een link naar een afspraken-boekingsdienst (Calendly, Cal.com, HubSpot Meetings, enz.)</label>
						<p class="description">Typerend voor cold-outreach/verkooppitches: de afzender wil dat jij een afspraak bij hén inplant. Een echte bezoeker stuurt in zijn eigen bericht nooit zo'n boekingslink mee.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Underscore in een naamveld</th>
					<td>
						<label><input type="checkbox" name="reject_name_underscore" value="1" <?php checked( $s['reject_name_underscore'] ); ?>> Weiger een naam met een underscore (_), zoals "mostbet_zlSl"</label>
						<p class="description">Een echte naam heeft nooit een underscore. Bots vullen hier vaak een merknaam met een willekeurig achtervoegsel in. Zie hieronder welke velden als naamveld tellen.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Cijferreeks in een naamveld</th>
					<td>
						<label><input type="checkbox" name="reject_name_digits" value="1" <?php checked( $s['reject_name_digits'] ); ?>> Weiger een naam met 6 of meer cijfers achter elkaar, zoals "NAERTREGE2323839NEYRTHYT"</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Domeinnaam in een naamveld</th>
					<td>
						<label><input type="checkbox" name="reject_name_domain" value="1" <?php checked( $s['reject_name_domain'] ); ?>> Weiger een naam met een link of domeinnaam, zoals "onhour.ru"</label>
						<p class="description">Een voorletter met een punt ervoor telt niet als domein, dus "J.de Vries" mag. <strong>Naamveld:</strong> een tekstveld met veld-ID <code>name</code>, of met naam, voornaam, achternaam of name in het label (zonder label: in de placeholder). Nooit een veld met e-mail, bedrijf, website, Instagram, LinkedIn of gebruikersnaam in het label, en nooit een veld met een webadres als placeholder.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Bericht met alleen een link</th>
					<td>
						<label><input type="checkbox" name="reject_textarea_only_url" value="1" <?php checked( $s['reject_textarea_only_url'] ); ?>> Weiger een berichtveld dat uit niets anders bestaat dan één link, zoals "http://47kg.kr/"</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Postcode controleren (NL/BE)</th>
					<td>
						<label><input type="checkbox" name="check_postcode" value="1" <?php checked( $s['check_postcode'] ); ?>> Weiger een postcode die geen Nederlandse (2921 AB of 2921ab) of Belgische (4 cijfers) postcode is</label>
						<p class="description">Een postcodeveld is een veld met postcode of post code in het label of het veld-ID, maar niet als er ook plaats, adres of straat in het label staat. De bezoeker krijgt bij het veld de melding "Vul een geldige postcode in, bijvoorbeeld 2921 AB." en kan het verbeteren. Een leeg veld wordt overgeslagen; of het verplicht is, regelt Elementor.</p>
					</td>
				</tr>
			</table>

			<h2>Uitgezonderde velden</h2>
			<p class="description">Velden die buiten alle checks vallen, per formulier. Bedoeld voor velden waar echte invoer op spam kan lijken, zoals achternamen, straatnamen en klachtomschrijvingen in een intakeformulier. Een uitgezonderd veld telt voor geen enkele check mee en komt niet in de logbestanden. De andere velden van dat formulier en alle andere formulieren worden gewoon gecontroleerd.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="exempt_fields">Velden per formulier</label></th>
					<td>
						<textarea id="exempt_fields" name="exempt_fields" rows="5" class="large-text code" placeholder="94c5fa7: field_c5fc26d, field_946b13c"><?php echo esc_textarea( mediamora_antispam_format_exempt_fields( $s['exempt_fields'] ) ); ?></textarea>
						<p class="description">Per regel één formulier: <code>formulier-ID: veld-ID, veld-ID</code>. Met <code>formulier-ID: *</code> vallen alle velden van dat formulier erbuiten. Lege regels en spaties tellen niet.</p>
						<p class="description"><strong>Formulier-ID:</strong> het element-ID van de formulierwidget. Dat staat in de broncode van de pagina als <code>&lt;input type="hidden" name="form_id" value="..."&gt;</code> in het formulier, en als <code>data-id</code> op de widget. Niet het veld "Formulier-ID" onder Extra opties in Elementor. Staat hetzelfde formulier op meer pagina's, kijk dan per pagina, want een gekopieerd formulier kan een ander ID hebben.</p>
						<p class="description"><strong>Veld-ID:</strong> in Elementor bij het veld onder Geavanceerd &gt; ID, bijvoorbeeld <code>field_c5fc26d</code>. In de broncode is het het deel tussen de haken in <code>name="form_fields[...]"</code>.</p>
					</td>
				</tr>
			</table>

			<h2>Geavanceerd: detectiedrempels</h2>
			<p class="description">De standaardwaardes zijn getest en werken goed op de meeste sites. Alleen aanpassen als je een concrete reden hebt, bijvoorbeeld op basis van het dagrapport.</p>

			<h3>Onleesbare tekst (gibberish)</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="gibberish_min_length">Minimale lengte</label></th><td><input type="number" min="1" id="gibberish_min_length" name="gibberish_min_length" value="<?php echo esc_attr( $s['gibberish_min_length'] ); ?>" class="small-text"> letters</td></tr>
				<tr><th scope="row"><label for="gibberish_vowel_ratio">Klinker-ratio drempel</label></th><td><input type="number" min="0" max="1" step="0.01" id="gibberish_vowel_ratio" name="gibberish_vowel_ratio" value="<?php echo esc_attr( $s['gibberish_vowel_ratio'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="gibberish_transitions">Case-wisselingen drempel</label></th><td><input type="number" min="1" id="gibberish_transitions" name="gibberish_transitions" value="<?php echo esc_attr( $s['gibberish_transitions'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="gibberish_structure_length">Structuur-lengte drempel</label></th><td><input type="number" min="1" id="gibberish_structure_length" name="gibberish_structure_length" value="<?php echo esc_attr( $s['gibberish_structure_length'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="gibberish_threshold">Aantal signalen nodig (van de 3)</label></th><td><input type="number" min="1" max="3" id="gibberish_threshold" name="gibberish_threshold" value="<?php echo esc_attr( $s['gibberish_threshold'] ); ?>" class="small-text"></td></tr>
			</table>

			<h3>Links</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="max_links_textarea">Max. links in een berichtveld</label></th><td><input type="number" min="0" id="max_links_textarea" name="max_links_textarea" value="<?php echo esc_attr( $s['max_links_textarea'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="max_links_text">Max. links in een gewoon tekstveld</label></th><td><input type="number" min="0" id="max_links_text" name="max_links_text" value="<?php echo esc_attr( $s['max_links_text'] ); ?>" class="small-text"><p class="description">Een volledige link met http(s):// mag alleen als die het hele veld vult. E-mailadressen tellen nergens mee als link.</p></td></tr>
			</table>

			<h3>Dubbele inhoud</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="duplicate_min_length">Minimale lengte om mee te tellen</label></th><td><input type="number" min="1" id="duplicate_min_length" name="duplicate_min_length" value="<?php echo esc_attr( $s['duplicate_min_length'] ); ?>" class="small-text"> tekens</td></tr>
			</table>

			<h3>Niet-Latijns schrift</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="non_latin_min_letters">Minimaal aantal letters</label></th><td><input type="number" min="1" id="non_latin_min_letters" name="non_latin_min_letters" value="<?php echo esc_attr( $s['non_latin_min_letters'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="non_latin_ratio">Latijns-ratio drempel</label></th><td><input type="number" min="0" max="1" step="0.01" id="non_latin_ratio" name="non_latin_ratio" value="<?php echo esc_attr( $s['non_latin_ratio'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="non_latin_min_count">Absoluut aantal niet-Latijnse letters</label></th><td><input type="number" min="1" id="non_latin_min_count" name="non_latin_min_count" value="<?php echo esc_attr( $s['non_latin_min_count'] ); ?>" class="small-text"></td></tr>
			</table>

			<h3>Herhaling (keyword stuffing)</h3>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="repetition_min_words">Minimaal aantal woorden</label></th><td><input type="number" min="1" id="repetition_min_words" name="repetition_min_words" value="<?php echo esc_attr( $s['repetition_min_words'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="repetition_min_word_length">Minimale woordlengte</label></th><td><input type="number" min="1" id="repetition_min_word_length" name="repetition_min_word_length" value="<?php echo esc_attr( $s['repetition_min_word_length'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="repetition_min_count">Minimaal aantal herhalingen</label></th><td><input type="number" min="1" id="repetition_min_count" name="repetition_min_count" value="<?php echo esc_attr( $s['repetition_min_count'] ); ?>" class="small-text"></td></tr>
				<tr><th scope="row"><label for="repetition_max_ratio">Max. aandeel van het bericht</label></th><td><input type="number" min="0" max="1" step="0.01" id="repetition_max_ratio" name="repetition_max_ratio" value="<?php echo esc_attr( $s['repetition_max_ratio'] ); ?>" class="small-text"></td></tr>
			</table>

			<?php submit_button( 'Instellingen opslaan', 'primary', 'mediamora_antispam_save' ); ?>
		</form>
	</div>
	<?php
}

// =====================================================================
// VALIDATIE
// =====================================================================

add_action( 'elementor_pro/forms/validation', 'mediamora_antispam_validate_form', 10, 2 );

/**
 * Hoofdvalidatie. Loopt eenmaal door alle velden en past alle checks toe.
 *
 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
 */
function mediamora_antispam_validate_form( $record, $ajax_handler ) {

	$s = mediamora_antispam_settings();

	// Veldtypes waar we deze checks niet op toepassen.
	$skip_types = array(
		'email', // een domeinnaam is een verplicht, normaal onderdeel van elk e-mailadres, geen spamsignaal
		'select',
		'radio',
		'checkbox',
		'acceptance',
		'upload',
		'hidden',
		'date',
		'time',
		'number',
		'url',
		'password', // niet beoordelen en nooit loggen, ook niet in debug.txt
		'recaptcha',
		'recaptcha_v3',
		'honeypot',
	);

	// Uitgezonderde velden gelden alleen voor het formulier waar ze bij staan.
	$form_id = (string) $record->get_form_settings( 'id' );

	// De placeholders staan niet in de velden van het record, wel in de
	// instellingen van het formulier.
	$placeholders = array();
	$form_fields  = $record->get_form_settings( 'form_fields' );
	if ( is_array( $form_fields ) ) {
		foreach ( $form_fields as $form_field ) {
			if ( isset( $form_field['custom_id'] ) ) {
				$placeholders[ (string) $form_field['custom_id'] ] = isset( $form_field['placeholder'] ) ? (string) $form_field['placeholder'] : '';
			}
		}
	}

	$fields              = $record->get( 'fields' );
	$long_values         = array();
	$near_misses         = array();
	$submission_rejected = false;

	foreach ( $fields as $id => $field ) {

		$type      = isset( $field['type'] ) ? $field['type'] : 'text';
		$raw_value = isset( $field['value'] ) ? $field['value'] : '';

		// Sommige veldtypes (zoals een samengesteld "Naam" veld met
		// voornaam/achternaam als subvelden) leveren een array i.p.v. een
		// string. Zonder deze afhandeling zou (string) $array simpelweg
		// "Array" opleveren, en dan wordt er nooit iets herkend.
		if ( is_array( $raw_value ) ) {
			$value = trim( implode( ' ', array_map( 'strval', $raw_value ) ) );
		} else {
			$value = trim( (string) $raw_value );
		}

		// Uitgezonderd veld: geen enkele check, ook niet de dubbele inhoud of
		// het vangnet, en de waarde komt nergens in een log, ook niet in debug.txt.
		if ( mediamora_antispam_is_exempt_field( $s['exempt_fields'], $form_id, $id ) ) {
			if ( $s['debug'] ) {
				mediamora_antispam_debug_log( $id, $type, '(niet gelogd)', false, null, 'overgeslagen (uitgezonderd veld)' );
			}
			continue;
		}

		// Zonder label telt de placeholder als label: veel formulieren tonen
		// alleen een placeholder als "Je naam".
		$placeholder = isset( $placeholders[ $id ] ) ? $placeholders[ $id ] : '';
		$label       = isset( $field['title'] ) ? trim( (string) $field['title'] ) : '';
		if ( '' === $label ) {
			$label = $placeholder;
		}

		// Postcode: vóór de skip-types, want een postcodeveld kan als
		// nummerveld gebouwd zijn. Eigen melding, zodat een bezoeker met een
		// tikfout ziet wat er mis is en het kan verbeteren.
		if ( '' !== $value && $s['check_postcode'] && mediamora_antispam_is_postcode_field( $id, $type, $label ) && ! mediamora_antispam_is_valid_postcode( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Vul een geldige postcode in, bijvoorbeeld 2921 AB.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'ongeldige postcode', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		if ( '' === $value || in_array( $type, $skip_types, true ) ) {
			if ( $s['debug'] ) {
				mediamora_antispam_debug_log( $id, $type, 'password' === $type ? '(niet gelogd)' : $value, false, null, 'overgeslagen (leeg of skip-type)' );
			}
			continue;
		}

		// Website- en profielvelden: daar hoort een domein of accountnaam in,
		// dus geen check op onleesbare tekst en geen vangnet voor de link.
		$is_profile      = mediamora_antispam_is_profile_field( $label, $placeholder, $value );
		$is_link         = mediamora_has_link_injection( $value, $type );
		$gibberish_score = mediamora_gibberish_score( $value );
		$is_gibberish    = ! $is_link && ! $is_profile && $gibberish_score['is_gibberish'];

		if ( $s['debug'] ) {
			mediamora_antispam_debug_log( $id, $type, $value, $is_link, $gibberish_score, 'beoordeeld' );
		}

		// Check 0: naamveld met een underscore, een cijferreeks of een domein
		if ( mediamora_antispam_is_name_field( $id, $type, $label, $placeholder ) ) {
			$name_reason = mediamora_antispam_name_reason( $value );
			if ( '' !== $name_reason ) {
				$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
				mediamora_antispam_log( $name_reason, $id, $type, $value );
				$submission_rejected = true;
				continue;
			}
		}

		// Check 1: link injection (BBCode, te veel URLs voor het veldtype, of
		// een berichtveld met alleen een link)
		if ( $is_link ) {
			$only_url = 'textarea' === $type && $s['reject_textarea_only_url'] && mediamora_antispam_is_only_url( $value );
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( $only_url ? 'bericht is alleen een link' : 'verdachte link', $id, $type, $value );
			$submission_rejected = true;
			continue; // dit veld is al afgekeurd, geen zin om ook nog op gibberish te checken
		}

		// Check 1b: niet-Latijns schrift, ook zonder link (alleen als expliciet ingeschakeld)
		if ( $s['reject_non_latin_text'] && mediamora_is_mostly_non_latin( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'niet-Latijns schrift', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1c: bericht noemt de domeinnaam van de site zelf (bulk-outreach patroon)
		if ( $s['reject_own_domain_mention'] && mediamora_mentions_own_domain( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'noemt eigen domeinnaam', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1d: verkorte URL-services (bit.ly, tinyurl, psee.io, enz.)
		if ( $s['reject_shortened_urls'] && mediamora_contains_shortened_url( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'verkorte URL', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1e: afspraken-boekingslink (Calendly, Cal.com, enz. - cold-outreach patroon)
		if ( $s['reject_scheduling_links'] && mediamora_contains_scheduling_link( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'boekingslink', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 2: automatisch gegenereerde ("random") tekst
		if ( $is_gibberish ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'onleesbare tekst', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 2b: overmatige woordherhaling (keyword stuffing)
		if ( mediamora_has_excessive_repetition( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'herhaalde trefwoorden', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Dit veld is op zichzelf geaccepteerd. Vangnet: zat het griezelig
		// dicht bij een weigeringsdrempel? Dan verzamelen we dat, maar
		// loggen het nog niet: een ander veld verderop, of de
		// dubbele-inhoud check hieronder, kan de hele inzending alsnog
		// laten weigeren, en die uiteindelijke status willen we erbij
		// vermelden.
		if ( $s['nearmiss_alert_enabled'] ) {
			$near_miss_reasons = mediamora_antispam_find_near_misses( $value, $type, $gibberish_score, $is_profile );
			if ( ! empty( $near_miss_reasons ) ) {
				$near_misses[] = array(
					'id'      => $id,
					'type'    => $type,
					'value'   => $value,
					'reasons' => $near_miss_reasons,
				);
			}
		}

		// Bewaren voor de duplicate-check hieronder
		if ( mb_strlen( $value ) > $s['duplicate_min_length'] ) {
			$long_values[ $id ] = $value;
		}
	}

	// Check 3: identieke lange inhoud in meerdere velden (bot die overal dezelfde tekst plakt)
	if ( count( $long_values ) > 1 && count( $long_values ) !== count( array_unique( $long_values ) ) ) {
		foreach ( $long_values as $id => $value ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora-toolkit' ) );
			mediamora_antispam_log( 'dubbele inhoud', $id, 'n.v.t.', $value );
			$submission_rejected = true;
		}
	}

	// Nu pas de verzamelde near-misses loggen, met de status van ONZE
	// EIGEN checks erbij. Let op: dit zegt alleen iets over wat DEZE
	// plugin heeft gedaan. Elementor Pro heeft een eigen, aparte
	// honeypot-bescherming die volledig buiten onze code om kan
	// ingrijpen: een inzending kan dus "door onze checks heen" komen en
	// alsnog nergens terechtkomen omdat Elementor's eigen mechanisme hem
	// zelfstandig heeft geweigerd. Dit kunnen wij niet zien of loggen.
	if ( ! empty( $near_misses ) ) {

		$status = $submission_rejected
			? 'door een ander veld alsnog geweigerd door ONZE checks'
			: 'door al onze eigen checks heen (zegt niets over Elementor\'s eigen honeypot of andere maatregelen, die hier los van kunnen ingrijpen)';

		foreach ( $near_misses as $near_miss ) {
			mediamora_antispam_nearmiss_log( $near_miss['id'], $near_miss['type'], $near_miss['value'], $near_miss['reasons'], $status );
		}
	}
}

/**
 * Herkent verkorte URL-services en andere bekende, vrijwel altijd
 * misbruikte wegwerp-linkdiensten (zoals telegra.ph, Telegram's gratis
 * publiceerplatform, veelgebruikt voor crypto-scampagina's omdat het
 * gratis is en moeilijk snel offline te halen). Functioneel gedraagt dit
 * zich hetzelfde als een verkorte URL: het is altijd een wegwerp-link,
 * nooit iemands eigen site, dus dezelfde lijst en logica.
 *
 * Werkt met een lijst van bekende diensten. Die lijst is per definitie
 * nooit compleet: spammers duiken steeds op met nieuwe of minder bekende
 * verkorters, en die glippen er dus doorheen. Dat is sinds 5.4 een
 * bewuste keuze. Er zat eerder een structurele herkenning bij die niet
 * van de naam afhing, maar die was kapot en bleek bij reparatie te veel
 * legitieme links te raken. Zie de changelog bij 5.4 in de header voor
 * de meetgegevens en voor de voorwaarde waaronder hij terug zou kunnen.
 *
 * Elke (mogelijke) verkorte/misbruikte URL verdient direct afkeuring,
 * ongeacht het aantal.
 *
 * @param string $value
 * @return bool
 */
function mediamora_contains_shortened_url( $value ) {

	$shorteners = array(
		'bit\.ly',
		'tinyurl\.com',
		'ow\.ly',
		'short\.link',
		'psee\.io',
		'goo\.gl',
		'buff\.ly',
		'adf\.ly',
		'bitly\.com',
		'tiny\.cc',
		'tr\.im',
		'is\.gd',
		'snipr\.com',
		'snipurl\.com',
		'cl\.lk',
		'lnk\.co',
		'shortened\.me',
		'sh\.st',
		'go2l\.ink',
		'shorturl\.at',
		'1url\.com',
		'rlnk\.co',
		'u\.to',
		'v\.gd',
		'short2url\.com',
		'clickto\.cc',
		'telegra\.ph',
	);

	return mediamora_antispam_matches_domain( $value, $shorteners );
}

/**
 * Herkent of een tekst de domeinnaam van de site zelf noemt. Typisch voor
 * bulk-outreach/verkooppitches die met een sjabloon-tool naar veel sites
 * tegelijk worden gestuurd, met de domeinnaam als ingevoegde variabele
 * ("Ik zag net [domeinnaam] en..."). Een echte bezoeker die het
 * contactformulier van een site invult heeft geen reden om de naam van
 * die site aan zichzelf te typen.
 *
 * @param string $value
 * @return bool
 */
function mediamora_mentions_own_domain( $value ) {

	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( empty( $host ) ) {
		return false;
	}

	$host = preg_replace( '/^www\./i', '', $host );

	return false !== stripos( $value, $host );
}

/**
 * Herkent links naar afspraken-boekingsdiensten (Calendly, Cal.com,
 * HubSpot Meetings, etc.). Typerend voor cold-outreach/verkooppitches: de
 * afzender wil dat de ontvanger een afspraak bij HEN inplant. Een echte
 * bezoeker die een contactformulier invult stuurt in zijn eigen bericht
 * nooit zo'n boekingslink mee, dat is een tool die uitsluitend door de
 * afzender van dit soort pitches wordt gebruikt.
 *
 * @param string $value
 * @return bool
 */
function mediamora_contains_scheduling_link( $value ) {

	$schedulers = array(
		'calendly\.com',
		'cal\.com',
		'meetings\.hubspot\.com',
		'acuityscheduling\.com',
		'doodle\.com',
		'chilipiper\.com',
		'youcanbook\.me',
		'appointlet\.com',
		'setmore\.com',
		'schedulicity\.com',
		'oncehub\.com',
		'zcal\.co',
		'savvycal\.com',
		'koalendar\.com',
	);

	return mediamora_antispam_matches_domain( $value, $schedulers );
}

/**
 * Komt een van de domeinen als los domein in de tekst voor?
 *
 * E-mailadressen gaan er eerst uit, zoals bij het tellen van links. Het
 * domein moet daarna los staan: geen letter, cijfer of streepje ervoor of
 * erna. Zonder die grens raakte cal.com ook medical.com, en u.to ook
 * "van u.Tot ziens". Een punt of slash ervoor mag wel, dus www.bit.ly en
 * https://bit.ly/abc worden nog steeds herkend.
 *
 * @param string   $value
 * @param string[] $domains Reguliere expressies, met de punt al ge-escaped.
 * @return bool
 */
function mediamora_antispam_matches_domain( $value, $domains ) {

	$value   = mediamora_antispam_strip_emails( $value );
	$pattern = '/(?<![a-z0-9-])(?:' . implode( '|', $domains ) . ')(?![a-z0-9-])/i';

	return (bool) preg_match( $pattern, $value );
}

/**
 * Haalt e-mailadressen uit een tekst. Het domein in "jan@voorbeeld.nl" is
 * een normaal onderdeel van het adres, geen link.
 *
 * @param string $value
 * @return string
 */
function mediamora_antispam_strip_emails( $value ) {
	return (string) preg_replace( '/[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}/i', ' ', $value );
}

/**
 * Telt de links in een tekst: volledige URLs met http(s):// en kale
 * domeinnamen (bijvoorbeeld "voorbeeld.ru" zonder "http://" ervoor).
 *
 * E-mailadressen gaan er eerst uit. Het domein in "jan@voorbeeld.nl" is
 * een normaal onderdeel van het adres, geen link, en een bezoeker die in
 * een bericht of in een als tekstveld gebouwd e-mailveld zijn adres
 * noemt, mag daar niet op worden afgekeurd.
 *
 * @param string $value
 * @return array{protocol: int, bare: int, total: int}
 */
function mediamora_antispam_count_links( $value ) {

	$value = mediamora_antispam_strip_emails( $value );

	$protocol_urls = preg_match_all( '/https?:\/\/\S+/i', $value );

	// Gevonden volledige URLs uit de tekst halen, zodat een domein daarbinnen
	// niet nog een keer meetelt bij de kale-domeinen check hieronder.
	$value_without_urls = preg_replace( '/https?:\/\/\S+/i', ' ', $value );

	$bare_domains = preg_match_all(
		'/\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:ru|su|com|net|org|info|biz|nl|be|de|fr|eu|io|co|me|shop|online|site|xyz|top|club|store|website|space|pro|vip|icu|cn|ua|by|kz|pl|it|es|uk|us)\b/i',
		$value_without_urls
	);

	return array(
		'protocol' => (int) $protocol_urls,
		'bare'     => (int) $bare_domains,
		'total'    => (int) $protocol_urls + (int) $bare_domains,
	);
}

/**
 * Of een veld uit precies één volledige URL bestaat en verder niets,
 * zoals bij een als tekstveld gebouwd "Website"-veld waarin de bezoeker
 * het adres uit de adresbalk plakt.
 *
 * @param string $value
 * @return bool
 */
function mediamora_antispam_is_only_url( $value ) {
	return (bool) preg_match( '/^https?:\/\/\S+$/i', trim( $value ) );
}

/**
 * Of een placeholder een webadres is, zoals "https://", "www.jouwsite.nl"
 * of "jouwsite.nl". Een veld met zo'n placeholder vraagt om een link.
 *
 * @param string $placeholder
 * @return bool
 */
function mediamora_antispam_has_url_placeholder( $placeholder ) {

	$placeholder = (string) $placeholder;

	if ( '' === trim( $placeholder ) ) {
		return false;
	}

	return (bool) preg_match( '/https?:|www\./i', $placeholder ) || mediamora_antispam_count_links( $placeholder )['total'] > 0;
}

/**
 * Of een veld om een website of een profiel vraagt: Website, Instagram of
 * LinkedIn in het label, een webadres als placeholder, of een waarde die
 * met @ begint ("@hestervanrij"). Daar hoort een domeinnaam of een
 * accountnaam in, en die lijken voor de check op onleesbare tekst en het
 * vangnet "link in kort veld" op spam.
 *
 * @param string $label       Het label, of de placeholder als het label leeg is.
 * @param string $placeholder
 * @param string $value
 * @return bool
 */
function mediamora_antispam_is_profile_field( $label, $placeholder, $value ) {

	if ( preg_match( '/website|instagram|linkedin/i', (string) $label ) ) {
		return true;
	}

	if ( mediamora_antispam_has_url_placeholder( $placeholder ) ) {
		return true;
	}

	return 0 === strpos( ltrim( (string) $value ), '@' );
}

/**
 * Of een veld een naamveld is: een tekstveld met veld-ID "name", of met
 * naam, voornaam, achternaam of name in het label. Nooit een veld met
 * e-mail, bedrijf(snaam), website, Instagram, LinkedIn of gebruikersnaam in
 * het label, en nooit een veld met een webadres als placeholder: daar
 * horen een domein, cijfers of een underscore er gewoon in.
 *
 * @param string $field_id    De custom_id van het veld.
 * @param string $type
 * @param string $label       Het label, of de placeholder als het label leeg is.
 * @param string $placeholder
 * @return bool
 */
function mediamora_antispam_is_name_field( $field_id, $type, $label, $placeholder ) {

	if ( 'text' !== $type || mediamora_antispam_has_url_placeholder( $placeholder ) ) {
		return false;
	}

	if ( preg_match( '/e-?mail|bedrijf|website|instagram|linkedin|gebruikersnaam|username/i', (string) $label ) ) {
		return false;
	}

	return 'name' === (string) $field_id || (bool) preg_match( '/naam|name/i', (string) $label );
}

/**
 * Of een naam een link of domeinnaam bevat. Een voorletter met een punt
 * gaat er eerst uit, zodat "J.de Vries" niet als het domein "J.de" telt.
 *
 * @param string $value
 * @return bool
 */
function mediamora_antispam_name_has_domain( $value ) {

	$value = (string) preg_replace( '/(?<![\p{L}\p{N}])\p{L}\./u', ' ', $value );

	return (bool) preg_match( '/www\./i', $value ) || mediamora_antispam_count_links( $value )['total'] > 0;
}

/**
 * De checks op een naamveld, elk met een eigen schakelaar.
 *
 * @param string $value
 * @return string De reden voor de log, leeg als de naam goed is.
 */
function mediamora_antispam_name_reason( $value ) {

	$s = mediamora_antispam_settings();

	if ( $s['reject_name_underscore'] && false !== strpos( $value, '_' ) ) {
		return 'underscore in naamveld';
	}

	if ( $s['reject_name_digits'] && preg_match( '/\d{6,}/', $value ) ) {
		return 'cijferreeks in naamveld';
	}

	if ( $s['reject_name_domain'] && mediamora_antispam_name_has_domain( $value ) ) {
		return 'domeinnaam in naamveld';
	}

	return '';
}

/**
 * Of een veld een postcodeveld is: een tekst-, telefoon- of nummerveld met
 * postcode of post code in het label of het veld-ID, hoofdletters maken
 * niet uit. Niet als het label ook om een plaats, adres of straat vraagt,
 * zoals "Postcode en woonplaats": daar hoort meer in dan alleen de postcode.
 *
 * @param string $field_id De custom_id van het veld.
 * @param string $type
 * @param string $label    Het label, of de placeholder als het label leeg is.
 * @return bool
 */
function mediamora_antispam_is_postcode_field( $field_id, $type, $label ) {

	if ( ! in_array( $type, array( 'text', 'tel', 'number' ), true ) ) {
		return false;
	}

	if ( preg_match( '/plaats|adres|straat|city|address|street/i', (string) $label ) ) {
		return false;
	}

	return (bool) preg_match( '/post[\s_-]?code/i', (string) $label . ' ' . (string) $field_id );
}

/**
 * Of een waarde een Nederlandse of Belgische postcode is. Nederlands: vier
 * cijfers zonder 0 vooraan, eventueel een spatie, twee letters ("2921 AB",
 * "2921ab"). Belgisch: vier cijfers. Witruimte eromheen telt niet.
 *
 * @param string $value
 * @return bool
 */
function mediamora_antispam_is_valid_postcode( $value ) {
	return (bool) preg_match( '/^(?:[1-9][0-9]{3} ?[a-z]{2}|[0-9]{4})$/i', trim( (string) $value ) );
}

/**
 * Herkent link injection. BBCode [url=] is in geen enkel veld legitiem.
 * Kale domeinnamen worden hetzelfde behandeld als volledige URLs, want
 * spam-bots laten het protocol vaak expres weg om linkherkenning te
 * omzeilen. E-mailadressen tellen niet mee, zie
 * mediamora_antispam_count_links().
 *
 * Voor het aantal toegestane links geldt een drempel per veldtype, allebei
 * in te stellen: een berichtveld kan best 1-2 legitieme links bevatten, en
 * in een gewoon tekstveld noemt een echte bezoeker ook weleens een
 * domeinnaam ("via linkedin.com", "Website: bedrijf.nl").
 *
 * Een volledige link met http(s):// in een gewoon tekstveld mag alleen als
 * die het hele veld vult. Een link met tekst eromheen in een kort veld
 * ("Beste SEO https://...") is het typische botpatroon en wordt geweigerd.
 *
 * Uitzondering op die drempels: een link gecombineerd met overwegend
 * niet-Latijns schrift wordt altijd geweigerd, ongeacht het aantal. Een
 * berichtveld met niets anders dan één link ook, als die check aan staat.
 *
 * @param string $value
 * @param string $type
 * @return bool
 */
function mediamora_has_link_injection( $value, $type ) {

	$s = mediamora_antispam_settings();

	if ( preg_match( '/\[url=/i', $value ) ) {
		return true;
	}

	$links     = mediamora_antispam_count_links( $value );
	$url_count = $links['total'];

	if ( $url_count < 1 ) {
		return false;
	}

	if ( mediamora_is_mostly_non_latin( $value ) ) {
		return true;
	}

	if ( 'textarea' === $type ) {
		// Een bericht dat alleen uit een link bestaat, is geen bericht.
		if ( $s['reject_textarea_only_url'] && mediamora_antispam_is_only_url( $value ) ) {
			return true;
		}
		return $url_count > $s['max_links_textarea'];
	}

	if ( $url_count > $s['max_links_text'] ) {
		return true;
	}

	return $links['protocol'] > 0 && ! mediamora_antispam_is_only_url( $value );
}

/**
 * Bepaalt of een tekst niet-Latijns schrift bevat (Cyrillisch, Arabisch,
 * Chinees, etc.), op twee manieren: een absoluut aantal niet-Latijnse
 * letters (vangt bijv. Cyrillische tekst met Latijnse merknamen ertussen),
 * of anders de verhouding Latijns/totaal bij voldoende tekst.
 *
 * Gebruikt PCRE Unicode-properties; als de server-PCRE die niet
 * ondersteunt, wordt fail-safe niets gerapporteerd als niet-Latijns.
 *
 * @param string $value
 * @return bool
 */
function mediamora_is_mostly_non_latin( $value ) {

	$s = mediamora_antispam_settings();

	$total_letters = @preg_match_all( '/\p{L}/u', $value );
	$latin_letters = @preg_match_all( '/\p{Latin}/u', $value );

	if ( false === $total_letters || false === $latin_letters || 0 === $total_letters ) {
		return false;
	}

	$non_latin_letters = $total_letters - $latin_letters;

	if ( $non_latin_letters >= $s['non_latin_min_count'] ) {
		return true;
	}

	if ( $total_letters < $s['non_latin_min_letters'] ) {
		return false;
	}

	return ( $latin_letters / $total_letters ) < $s['non_latin_ratio'];
}

/**
 * Berekent de gibberish-signalen voor een stuk tekst en geeft alle
 * tussenliggende meetwaarden terug (handig voor debugging), naast het
 * eindoordeel.
 *
 * 1. Klinker-ratio: normale taal zit rond de 35-45% klinkers.
 * 2. Case-wisselingen: een random string wisselt vaak grillig tussen
 *    hoofdletters en kleine letters.
 * 3. Geen natuurlijke structuur: langere tekst zonder spaties/leestekens
 *    is atypisch voor door mensen getypte input.
 *
 * @param string $value
 * @return array
 */
function mediamora_gibberish_score( $value ) {

	$s = mediamora_antispam_settings();

	$letters_only = preg_replace( '/[^A-Za-z\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{00FF}]/u', '', $value );

	if ( null === $letters_only ) {
		$letters_only = '';
	}

	$length = mb_strlen( $letters_only );

	$result = array(
		'length'                => $length,
		'vowel_ratio'           => null,
		'transitions'           => null,
		'has_natural_structure' => null,
		'suspicious'            => 0,
		'is_gibberish'          => false,
	);

	if ( $length < $s['gibberish_min_length'] ) {
		return $result;
	}

	$vowels      = preg_match_all( '/[aeiouAEIOU\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{00FF}]/u', $letters_only );
	$vowel_ratio = $vowels / $length;

	// Case-wisselingen PER WOORD tellen, niet over de hele tekst met
	// spaties eruit gehaald. Reden: als je spaties weghaalt en dan telt,
	// wordt elke overgang tussen twee keurig geschreven eigennamen of
	// zinnen ("Via Petra de Krom", of een bericht met meerdere zinnen)
	// ook als "wisseling" geteld, terwijl dat normale Titel-Case is. Een
	// los woord dat volledig kleine letters, volledig hoofdletters
	// (acroniem), of Titel-Case is (1 hoofdletter vooraan, rest klein)
	// is normaal en telt niet mee. Alleen een hoofdletter/kleine-letter-
	// wisseling op een onverwachte plek BINNEN een woord (zoals bij
	// bot-tekst) telt als signaal.
	$transitions = 0;
	$upper_class  = 'A-Z\x{00C0}-\x{00D6}\x{00D8}-\x{00DE}';
	$lower_class  = 'a-z\x{00E0}-\x{00F6}\x{00F8}-\x{00FF}';
	foreach ( preg_split( '/\s+/', $value ) as $word ) {

		$word_letters = preg_replace( '/[^A-Za-z\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{00FF}]/u', '', $word );
		$word_length  = mb_strlen( $word_letters );

		if ( $word_length < 2 ) {
			continue; // te kort om iets te zeggen
		}

		if ( preg_match( '/^[' . $lower_class . ']+$/u', $word_letters ) ) {
			continue; // helemaal kleine letters
		}

		if ( preg_match( '/^[' . $upper_class . ']+$/u', $word_letters ) ) {
			continue; // helemaal hoofdletters, acroniem
		}

		$first_char = mb_substr( $word_letters, 0, 1 );
		$rest       = mb_substr( $word_letters, 1 );

		if ( preg_match( '/^[' . $upper_class . ']$/u', $first_char ) && preg_match( '/^[' . $lower_class . ']+$/u', $rest ) ) {
			continue; // normale Titel-Case: "Petra", "Krom", "Hetty"
		}

		// Dit woord wijkt af van elk normaal patroon: tel de daadwerkelijke
		// wisselingen binnenin (los van de eerste letter, een hoofdletter
		// vooraan is immers altijd normaal).
		$prev_upper = null;
		for ( $i = 0; $i < $word_length; $i++ ) {
			$char     = mb_substr( $word_letters, $i, 1 );
			$is_upper = ( $char === mb_strtoupper( $char ) && $char !== mb_strtolower( $char ) );
			if ( null !== $prev_upper && $is_upper !== $prev_upper ) {
				$transitions++;
			}
			$prev_upper = $is_upper;
		}
	}

	$has_natural_structure = (bool) preg_match( '/[\s,.!?\'"-]/', $value );

	$suspicious = 0;

	if ( $vowel_ratio < $s['gibberish_vowel_ratio'] ) {
		$suspicious++;
	}
	if ( $transitions >= $s['gibberish_transitions'] ) {
		$suspicious++;
	}
	if ( ! $has_natural_structure && $length >= $s['gibberish_structure_length'] ) {
		$suspicious++;
	}

	$result['vowel_ratio']           = $vowel_ratio;
	$result['transitions']           = $transitions;
	$result['has_natural_structure'] = $has_natural_structure;
	$result['suspicious']            = $suspicious;
	$result['is_gibberish']          = ( $suspicious >= $s['gibberish_threshold'] );

	return $result;
}

/**
 * Herkent overmatige herhaling van eenzelfde woord binnen één veld, een
 * typisch patroon bij keyword-stuffing spam. Kijkt naar de verhouding
 * t.o.v. de totale berichtlengte, zodat een lang, natuurlijk bericht
 * automatisch meer ruimte krijgt dan een kort bericht.
 *
 * @param string $value
 * @return bool
 */
function mediamora_has_excessive_repetition( $value ) {

	$s = mediamora_antispam_settings();

	$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $value ), -1, PREG_SPLIT_NO_EMPTY );

	if ( false === $words ) {
		return false;
	}

	$total = count( $words );

	if ( $total < $s['repetition_min_words'] ) {
		return false;
	}

	$counts = array_count_values( $words );

	foreach ( $counts as $word => $count ) {
		if ( mb_strlen( $word ) < $s['repetition_min_word_length'] ) {
			continue;
		}
		if ( $count < $s['repetition_min_count'] ) {
			continue;
		}
		if ( ( $count / $total ) >= $s['repetition_max_ratio'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Vangnet: onderzoekt een GEACCEPTEERD veld op tekenen dat het griezelig
 * dicht bij een weigeringsdrempel zat. Dit is een heuristiek, geen
 * garantie: het vangt alleen twijfelgevallen die al bijna een bestaand
 * signaal raakten, niet compleet nieuwe spamvormen die nergens bij in de
 * buurt komen.
 *
 * @param string $value
 * @param string $type
 * @param array  $gibberish_score Resultaat van mediamora_gibberish_score() voor dit veld.
 * @param bool   $is_profile      Website- of profielveld: geen onleesbare tekst en geen link in kort veld.
 * @return string[] Lijst met redenen, leeg als er niets opvallends is.
 */
function mediamora_antispam_find_near_misses( $value, $type, $gibberish_score, $is_profile = false ) {

	$s       = mediamora_antispam_settings();
	$reasons = array();

	// Gibberish: precies 1 signaal te weinig om te weigeren.
	if ( ! $is_profile && $gibberish_score['suspicious'] > 0 && $gibberish_score['suspicious'] === ( $s['gibberish_threshold'] - 1 ) ) {
		$reasons[] = 'onleesbare tekst (net niet genoeg signalen)';
	}

	// Links: exact op de toegestane grens voor berichtvelden.
	if ( 'textarea' === $type ) {
		$url_count = mediamora_antispam_count_links( $value )['total'];
		if ( $url_count === (int) $s['max_links_textarea'] ) {
			$reasons[] = 'links (precies op de toegestane grens)';
		}
	}

	// Links in een gewoon tekstveld: sinds de grens daar niet meer 0 is,
	// elke doorgelaten link loggen, zodat zichtbaar wordt wat erdoor komt.
	if ( 'textarea' !== $type && ! $is_profile ) {
		$url_count = mediamora_antispam_count_links( $value )['total'];
		if ( $url_count > 0 ) {
			$reasons[] = sprintf( 'link in kort veld, doorgelaten (%d)', $url_count );
		}
	}

	// Niet-Latijns schrift: net onder de absolute drempel.
	$total_letters = @preg_match_all( '/\p{L}/u', $value );
	$latin_letters = @preg_match_all( '/\p{Latin}/u', $value );
	if ( false !== $total_letters && false !== $latin_letters && $total_letters > 0 ) {
		$non_latin = $total_letters - $latin_letters;
		if ( $non_latin > 0 && $non_latin < $s['non_latin_min_count'] ) {
			$reasons[] = 'niet-Latijns schrift (net onder de drempel)';
		}
	}

	// Herhaling: ratio net onder de drempel (binnen 5 procentpunt).
	$words = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $value ), -1, PREG_SPLIT_NO_EMPTY );
	if ( is_array( $words ) && count( $words ) >= $s['repetition_min_words'] ) {
		$total  = count( $words );
		$counts = array_count_values( $words );
		foreach ( $counts as $word => $count ) {
			if ( mb_strlen( $word ) < $s['repetition_min_word_length'] || $count < $s['repetition_min_count'] ) {
				continue;
			}
			$ratio = $count / $total;
			if ( $ratio >= ( $s['repetition_max_ratio'] - 0.05 ) && $ratio < $s['repetition_max_ratio'] ) {
				$reasons[] = 'herhaalde trefwoorden (net onder de drempel)';
				break;
			}
		}
	}

	return $reasons;
}

// =====================================================================
// LOGGING EN RAPPORTAGE
// =====================================================================

/**
 * Tijdelijke debug-logging: legt van elk beoordeeld veld het type, de
 * waarde en de details van beide checks vast, ongeacht of het veld wordt
 * geweigerd. Alleen actief als de debug-instelling aan staat.
 *
 * @param string     $field_id
 * @param string     $field_type
 * @param string     $value
 * @param bool       $is_link
 * @param array|null $gibberish_score
 * @param string     $status
 */
function mediamora_antispam_debug_log( $field_id, $field_type, $value, $is_link, $gibberish_score, $status ) {

	mediamora_antispam_ensure_log_dir();

	$debug_file = mediamora_antispam_debug_file();

	$snippet = str_replace( array( "\r", "\n" ), ' ', $value );
	$snippet = mb_substr( $snippet, 0, 80 );

	if ( is_array( $gibberish_score ) ) {
		$score_text = sprintf(
			'lengte=%d klinker-ratio=%s wisselingen=%s structuur=%s suspicious=%d/3 gibberish=%s',
			$gibberish_score['length'],
			null === $gibberish_score['vowel_ratio'] ? 'n.v.t. (te kort)' : round( $gibberish_score['vowel_ratio'], 2 ),
			null === $gibberish_score['transitions'] ? 'n.v.t.' : $gibberish_score['transitions'],
			null === $gibberish_score['has_natural_structure'] ? 'n.v.t.' : ( $gibberish_score['has_natural_structure'] ? 'ja' : 'nee' ),
			$gibberish_score['suspicious'],
			$gibberish_score['is_gibberish'] ? 'ja' : 'nee'
		);
	} else {
		$score_text = 'n.v.t.';
	}

	$line = sprintf(
		'%s | status: %s | veld-id: %s | type: %s | waarde: "%s" | link-check: %s | gibberish-detail: %s',
		mediamora_antispam_log_time(),
		$status,
		$field_id,
		$field_type,
		$snippet,
		$is_link ? 'ja' : 'nee',
		$score_text
	);

	file_put_contents( $debug_file, $line . "\n", FILE_APPEND | LOCK_EX );
}

// Opschonen op de bewaartermijn gebeurt in een eigen dagelijks cron-event,
// los van de mails: die draaien alleen als er iets is gelogd en als ze aan
// staan, en debug.txt loopt juist ook vol met inzendingen die gewoon zijn
// doorgelaten. Uitzetten, deactiveren en verwijderen halen het event weg,
// zie mediamora-toolkit.php en uninstall.php.
add_action( 'mediamora_antispam_prune_logs', 'mediamora_antispam_prune_logs' );

add_action( 'init', 'mediamora_antispam_plan_prune', 20 );

function mediamora_antispam_plan_prune() {
	if ( ! wp_next_scheduled( 'mediamora_antispam_prune_logs' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mediamora_antispam_prune_logs' );
	}
}

/**
 * Haalt uit log.txt, near-miss.txt en debug.txt de regels die ouder zijn dan
 * de bewaartermijn.
 */
function mediamora_antispam_prune_logs() {

	$s     = mediamora_antispam_settings();
	$grens = time() - $s['log_max_age'];

	foreach ( array( mediamora_antispam_log_file(), mediamora_antispam_nearmiss_file(), mediamora_antispam_debug_file() ) as $bestand ) {
		mediamora_antispam_prune_file( $bestand, $grens );
	}
}

/**
 * Haalt uit een logbestand de regels van voor $grens. Regels zonder
 * leesbare tijd gaan er ook uit. Is er niets verlopen, dan blijft het
 * bestand onaangeroerd.
 *
 * @param string $bestand
 * @param int    $grens Unix-tijd.
 */
function mediamora_antispam_prune_file( $bestand, $grens ) {

	if ( ! file_exists( $bestand ) ) {
		return;
	}

	$regels = file( $bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	if ( empty( $regels ) ) {
		return;
	}

	$blijft = array();

	foreach ( $regels as $regel ) {
		$tijd = mediamora_antispam_parse_line_timestamp( $regel );
		if ( false !== $tijd && $tijd >= $grens ) {
			$blijft[] = $regel;
		}
	}

	if ( count( $blijft ) === count( $regels ) ) {
		return;
	}

	file_put_contents(
		$bestand,
		implode( "\n", $blijft ) . ( empty( $blijft ) ? '' : "\n" ),
		LOCK_EX
	);
}

/**
 * Schrijft een regel naar het logbestand en plant (indien nodig) de
 * rapportmail in.
 *
 * @param string $reason
 * @param string $field_id
 * @param string $field_type
 * @param string $value
 */
function mediamora_antispam_log( $reason, $field_id, $field_type, $value ) {

	mediamora_antispam_ensure_log_dir();

	$snippet = str_replace( array( "\r", "\n" ), ' ', $value );
	$snippet = mb_substr( $snippet, 0, 200 );

	$line = sprintf(
		'%s | veld: %s (%s) | reden: %s | waarde: %s',
		mediamora_antispam_log_time(),
		$field_id,
		$field_type,
		$reason,
		$snippet
	);

	file_put_contents( mediamora_antispam_log_file(), $line . "\n", FILE_APPEND | LOCK_EX );

	$s = mediamora_antispam_settings();
	mediamora_antispam_plan_mail( 'mediamora_antispam_report', 'mediamora_antispam_last_report', $s['email_interval'] );
}

/**
 * Schrijft een regel naar het vangnet-logbestand en plant (indien nodig)
 * de vangnet-mail in.
 *
 * @param string   $field_id
 * @param string   $field_type
 * @param string   $value
 * @param string[] $reasons
 * @param string   $submission_status Wat er uiteindelijk met de hele inzending is gebeurd (niet alleen dit veld).
 */
function mediamora_antispam_nearmiss_log( $field_id, $field_type, $value, $reasons, $submission_status ) {

	mediamora_antispam_ensure_log_dir();

	$snippet = str_replace( array( "\r", "\n" ), ' ', $value );
	$snippet = mb_substr( $snippet, 0, 200 );

	$line = sprintf(
		'%s | veld: %s (%s) | vermoedelijke reden(en): %s | inzending: %s | waarde: %s',
		mediamora_antispam_log_time(),
		$field_id,
		$field_type,
		implode( ', ', $reasons ),
		$submission_status,
		$snippet
	);

	file_put_contents( mediamora_antispam_nearmiss_file(), $line . "\n", FILE_APPEND | LOCK_EX );

	$s = mediamora_antispam_settings();
	if ( $s['nearmiss_alert_enabled'] ) {
		mediamora_antispam_plan_mail( 'mediamora_antispam_nearmiss_alert', 'mediamora_antispam_last_nearmiss_alert', $s['nearmiss_email_interval'] );
	}
}

// De rapportmail en de vangnet-mail gaan via WP-cron, zodat een bezoeker
// bij het versturen van een formulier niet wacht op het herschrijven van
// het logbestand en op wp_mail(). De functies zelf controleren de periode
// nog een keer: draait een event toch te vroeg (bijvoorbeeld omdat de mail
// intussen uit en weer aan is gezet), dan plannen ze zich opnieuw in op het
// eind van de periode en doen verder niets.
add_action( 'mediamora_antispam_report', 'mediamora_antispam_maybe_send_report' );
add_action( 'mediamora_antispam_nearmiss_alert', 'mediamora_antispam_maybe_send_nearmiss_alert' );

/**
 * Plant een eenmalig cron-event in voor een van de mails, als er nog geen
 * klaarstaat. Is de periode sinds de vorige mail om, dan meteen; anders op
 * het eind van die periode. Zo wordt een logregel van vlak na een mail ook
 * gemaild als er daarna niets meer bij komt, en niet pas bij de volgende
 * weigering (of nooit, als de bewaartermijn hem eerder opruimt).
 *
 * @param string $hook
 * @param string $last_option Optie met het tijdstip van de vorige run.
 * @param int    $interval
 */
function mediamora_antispam_plan_mail( $hook, $last_option, $interval ) {

	if ( wp_next_scheduled( $hook ) ) {
		return;
	}

	$eind_periode = (int) get_option( $last_option, 0 ) + $interval;

	wp_schedule_single_event( max( time(), $eind_periode ), $hook );
}

/**
 * Plant een nieuwe poging in na een mislukte mail. Het tijdstip van de
 * vorige mail blijft dan staan, dus de volgende poging neemt dezelfde regels
 * weer mee. Bewust pas over een volle periode en niet meteen: bij een
 * blijvend kapotte mailinstelling komt er zo hooguit één mislukte poging (en
 * één Formuliermonitor-melding) per periode bij, in plaats van één bij elke
 * paginaweergave die WP-cron laat draaien.
 *
 * @param string $hook
 * @param int    $nu
 * @param int    $interval
 */
function mediamora_antispam_plan_retry( $hook, $nu, $interval ) {

	if ( wp_next_scheduled( $hook ) ) {
		return;
	}

	wp_schedule_single_event( $nu + $interval, $hook );
}

/**
 * Zorgt dat de logmap bestaat en afgeschermd is tegen direct web-bezoek,
 * en verhuist eerst wat er eventueel nog in de oude, vaste map staat.
 */
function mediamora_antispam_ensure_log_dir() {

	mediamora_antispam_maybe_migrate_log_dir();

	$dir = mediamora_antispam_log_dir();

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	if ( ! is_dir( $dir ) ) {
		return;
	}

	mediamora_antispam_write_log_dir_guards( $dir );
}

/**
 * Schrijft de afscherming in een logmap: de .htaccess en een lege index.php.
 *
 * Staat bewust los van mediamora_antispam_ensure_log_dir() en roept zelf
 * niets aan. De map komt als parameter binnen, niet uit
 * mediamora_antispam_log_dir(), zodat ook de verhuizing hem kan gebruiken
 * voor de nieuwe map zonder dat die twee elkaar over en weer aanroepen.
 *
 * @param string $dir Bestaande map, absoluut pad zonder slash aan het eind.
 * @return bool True als de .htaccess er daadwerkelijk staat met de juiste inhoud.
 */
function mediamora_antispam_write_log_dir_guards( $dir ) {

	/*
	 * Apache 2.4 schermt af met mod_authz_core (Require), 2.2 met mod_access_compat
	 * (Order/Deny). Draait een 2.4-server zonder mod_access_compat, dan is een kale
	 * "Deny from all" een onbekende directive en geeft de hele map een 500 in plaats
	 * van een nette 403. Daarom allebei de varianten, elk in zijn eigen IfModule-blok,
	 * zodat er per server precies een van de twee wordt gelezen.
	 *
	 * LiteSpeed leest .htaccess op dezelfde manier als Apache. Op Nginx doet dit
	 * bestand niets: daar moet de map in de serverconfiguratie worden afgeschermd.
	 */
	$regels = "<IfModule mod_authz_core.c>\n"
		. "\tRequire all denied\n"
		. "</IfModule>\n"
		. "<IfModule !mod_authz_core.c>\n"
		. "\tOrder allow,deny\n"
		. "\tDeny from all\n"
		. "</IfModule>\n";

	$htaccess = $dir . '/.htaccess';
	$huidig   = file_exists( $htaccess ) ? file_get_contents( $htaccess ) : false;

	// Ook herschrijven als er al een ander .htaccess ligt. Sites die op een oudere
	// versie zijn begonnen hebben de kale, foutgevoelige variant staan, en die wordt
	// zonder deze vergelijking nooit vervangen.
	if ( $regels !== $huidig && false === file_put_contents( $htaccess, $regels, LOCK_EX ) ) {
		return false;
	}

	// Vangnet voor servers die .htaccess negeren maar wel een directory-index tonen:
	// een lege index.php levert dan een blanco pagina in plaats van de bestandenlijst.
	// Beschermt niet tegen het rechtstreeks opvragen van log.txt zelf.
	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
	}

	return true;
}

/**
 * Verhuist de logbestanden uit de oude, vaste map naar de map met het
 * willekeurige achtervoegsel, en ruimt de oude map daarna op.
 *
 * Draait bij elke schrijfactie en eenmaal per beheerpagina, maar doet na
 * de eerste keer niets meer: zodra de oude map weg is, kost dit alleen nog
 * een is_dir(). Mislukt de verhuizing halverwege (rechten, vol filesysteem),
 * dan blijft de oude map staan en wordt het de volgende keer opnieuw
 * geprobeerd.
 */
function mediamora_antispam_maybe_migrate_log_dir() {

	$oud = mediamora_antispam_legacy_log_dir();

	if ( ! is_dir( $oud ) ) {
		return;
	}

	$nieuw = mediamora_antispam_log_dir();

	if ( $oud === $nieuw ) {
		return;
	}

	if ( ! is_dir( $nieuw ) ) {
		wp_mkdir_p( $nieuw );
	}

	// Nieuwe map kon niet worden aangemaakt: niets verplaatsen, want dan
	// zouden de logregels verdwijnen in plaats van verhuizen.
	if ( ! is_dir( $nieuw ) ) {
		return;
	}

	// Eerst afschermen, dan pas verhuizen. wp_mkdir_p() levert een kale map
	// op, en langs de admin_init-route komt er daarna niets meer langs dat de
	// .htaccess zou schrijven: dat gebeurt pas bij de eerstvolgende logregel.
	// Tot die tijd zouden de verhuisde inzendingen open en bloot staan.
	//
	// Lukt de afscherming niet, dan blijft alles staan waar het staat. De oude
	// map is wel afgeschermd, dus daar zijn de bestanden beter af dan in een
	// kale nieuwe map. Volgende keer opnieuw proberen.
	if ( ! mediamora_antispam_write_log_dir_guards( $nieuw ) ) {
		return;
	}

	foreach ( array( 'log.txt', 'near-miss.txt', 'debug.txt' ) as $bestand ) {

		$van  = $oud . '/' . $bestand;
		$naar = $nieuw . '/' . $bestand;

		if ( ! file_exists( $van ) ) {
			continue;
		}

		// Staat er op de nieuwe plek al een bestand (een eerdere verhuizing
		// die halverwege strandde, waarna er alweer gelogd is), dan de oude
		// regels erachter plakken in plaats van ze te overschrijven. De
		// volgorde klopt dan niet meer, maar elke regel draagt zijn eigen
		// tijdstempel en daar wordt op gefilterd, niet op de volgorde.
		if ( file_exists( $naar ) ) {
			$inhoud = file_get_contents( $van );
			if ( false !== $inhoud && '' !== $inhoud ) {
				file_put_contents( $naar, $inhoud, FILE_APPEND | LOCK_EX );
			}
			@unlink( $van );
			continue;
		}

		if ( @rename( $van, $naar ) ) {
			continue;
		}

		// Rename kan stuklopen op open_basedir of een ander filesysteem.
		// Dan kopieren, en het origineel pas weghalen als dat gelukt is.
		if ( @copy( $van, $naar ) ) {
			@unlink( $van );
		}
	}

	mediamora_antispam_remove_legacy_log_dir( $oud );
}

/**
 * Verwijdert de oude logmap, maar alleen als er behalve onze eigen
 * beschermbestanden niets meer in staat. Ligt er nog iets anders, dan
 * blijft de map inclusief .htaccess gewoon staan: liever een map die is
 * blijven hangen dan een map die we onderweg hebben opengezet.
 *
 * @param string $oud
 */
function mediamora_antispam_remove_legacy_log_dir( $oud ) {

	$inhoud = @scandir( $oud );

	if ( false === $inhoud ) {
		return;
	}

	$eigen = array( '.', '..', '.htaccess', 'index.php' );

	if ( array_diff( $inhoud, $eigen ) ) {
		return;
	}

	foreach ( array( '.htaccess', 'index.php' ) as $bestand ) {
		if ( file_exists( $oud . '/' . $bestand ) ) {
			@unlink( $oud . '/' . $bestand );
		}
	}

	@rmdir( $oud );
}

// Ook zonder nieuwe inzending verhuizen, zodat de oude map ook verdwijnt
// op een site waar het contactformulier voorlopig geen spam meer vangt.
add_action( 'admin_init', 'mediamora_antispam_maybe_migrate_log_dir' );

/**
 * Het tijdstip voor een logregel: in de tijdzone van de site, met de
 * afwijking van UTC erachter ("2026-11-20 15:03:11 +01:00"). Zo staan het
 * log en de mails in dezelfde tijd als de rest van de site, en is elke
 * regel ook buiten WordPress om terug te rekenen.
 *
 * @param int|null $tijd Unix-tijd, standaard nu.
 * @return string
 */
function mediamora_antispam_log_time( $tijd = null ) {
	return wp_date( 'Y-m-d H:i:s P', null === $tijd ? time() : $tijd );
}

/**
 * Haalt de timestamp uit een logregel (begint met "Y-m-d H:i:s +01:00 | ...").
 * Regels van voor 1.28.0 hebben geen tijdzone en staan in UTC.
 *
 * @param string $line
 * @return int|false
 */
function mediamora_antispam_parse_line_timestamp( $line ) {

	$parts   = explode( ' | ', $line, 2 );
	$stempel = trim( $parts[0] );

	if ( '' === $stempel ) {
		return false;
	}

	if ( ! preg_match( '/[+-]\d{2}:\d{2}$/', $stempel ) ) {
		$stempel .= ' UTC';
	}

	$timestamp = strtotime( $stempel );

	return false !== $timestamp ? $timestamp : false;
}

/**
 * Een logregel zoals die in een mail komt: met het tijdstip in de tijdzone
 * van de site, ook als de regel nog van voor 1.28.0 is en in UTC staat.
 *
 * @param string $line
 * @return string
 */
function mediamora_antispam_line_for_mail( $line ) {

	$timestamp = mediamora_antispam_parse_line_timestamp( $line );
	$parts     = explode( ' | ', $line, 2 );

	if ( false === $timestamp || ! isset( $parts[1] ) ) {
		return $line;
	}

	return mediamora_antispam_log_time( $timestamp ) . ' | ' . $parts[1];
}

/**
 * Vanaf welk moment logregels in de rapportmail of de vangnet-mail komen:
 * het tijdstip van de vorige run. Daardoor komt alles sinds de vorige mail
 * erin, hoe lang die ook geleden is; de periode is alleen de minimale tijd
 * tussen twee mails. Is er nog nooit een mail geweest, dan de afgelopen
 * periode. Nooit verder terug dan de bewaartermijn: wat daarvoor ligt,
 * hoort al uit het log en dus ook niet meer in een mail.
 *
 * Het venster loopt van dit moment tot en met de seconde vóór $nu, en $nu
 * wordt de volgende keer het nieuwe begin. Logtijden zijn op de seconde,
 * dus een regel uit de seconde van het verzenden kan net voor of net na
 * het inlezen geschreven zijn. Zo'n regel komt altijd in de volgende mail
 * en nooit in deze. Met >= aan het begin en < aan het eind valt elke
 * regel in precies één venster: niets dubbel, niets kwijt.
 *
 * @param int $last_run Tijdstip van de vorige run, 0 als die er nog niet was.
 * @param int $nu
 * @param int $interval
 * @param int $log_max_age
 * @return int Unix-tijd.
 */
function mediamora_antispam_mail_window_start( $last_run, $nu, $interval, $log_max_age ) {

	$start = $last_run > 0 ? $last_run : $nu - $interval;

	return max( $start, $nu - $log_max_age );
}

/**
 * Stuurt (indien ingeschakeld) maximaal 1x per ingestelde periode een
 * samenvattend rapport met ALLE geweigerde inzendingen sinds de vorige
 * mail, en ruimt tegelijk logregels op die ouder zijn dan de bewaartermijn.
 * De opschoning gebeurt altijd, ook als de rapportmail zelf uit staat.
 */
function mediamora_antispam_maybe_send_report() {

	$s  = mediamora_antispam_settings();
	$nu = time();

	$last_run = (int) get_option( 'mediamora_antispam_last_report', 0 );

	if ( ( $nu - $last_run ) < $s['email_interval'] ) {
		mediamora_antispam_plan_mail( 'mediamora_antispam_report', 'mediamora_antispam_last_report', $s['email_interval'] );
		return;
	}

	$log_file = mediamora_antispam_log_file();

	if ( ! file_exists( $log_file ) ) {
		return;
	}

	$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	if ( empty( $lines ) ) {
		return;
	}

	$cutoff_email     = mediamora_antispam_mail_window_start( $last_run, $nu, $s['email_interval'], $s['log_max_age'] );
	$cutoff_retention = $nu - $s['log_max_age'];

	$recent_for_email   = array();
	$kept_for_retention = array();

	foreach ( $lines as $line ) {

		$timestamp = mediamora_antispam_parse_line_timestamp( $line );

		if ( false === $timestamp ) {
			continue;
		}

		if ( $timestamp >= $cutoff_retention ) {
			$kept_for_retention[] = $line;
		}

		if ( $timestamp >= $cutoff_email && $timestamp < $nu ) {
			$recent_for_email[] = $line;
		}
	}

	// Logbestand opschonen op bewaartermijn (geen persoonsgegevens onbeperkt bewaren).
	// Gebeurt altijd, onafhankelijk van of de mail hieronder verstuurd wordt.
	file_put_contents(
		$log_file,
		implode( "\n", $kept_for_retention ) . ( empty( $kept_for_retention ) ? '' : "\n" ),
		LOCK_EX
	);

	// Ook als de rapportmail uit staat of er niets te melden is: wat er nu
	// in het log staat, hoeft later niet alsnog gemaild te worden.
	if ( ! $s['report_enabled'] || empty( $recent_for_email ) ) {
		update_option( 'mediamora_antispam_last_report', $nu, false );
		return;
	}

	$count = count( $recent_for_email );
	$site  = wp_parse_url( home_url(), PHP_URL_HOST );

	$body  = sprintf(
		"De anti-spam check op je Elementor formulier(en) heeft de afgelopen periode %d inzending(en) geweigerd.\n\n",
		$count
	);
	$body .= "Details:\n\n";
	$body .= implode( "\n", array_map( 'mediamora_antispam_line_for_mail', $recent_for_email ) );
	$body .= "\n\nVolledige log (incl. de regels uit eerdere rapporten, binnen de bewaartermijn) staat in " . mediamora_antispam_log_path_label( $log_file ) . " op de server. Die mapnaam verschilt per site.";

	$verstuurd = wp_mail(
		$s['alert_email'],
		sprintf( '[%s] Anti-spam rapport: %d geweigerde inzending(en)', $site, $count ),
		$body
	);

	// Het tijdstip pas opslaan als de mail echt weg is. Mislukt hij, dan
	// blijft de vorige mail het begin en komen deze regels in de volgende
	// poging opnieuw mee, in plaats van stil te verdwijnen.
	if ( $verstuurd ) {
		update_option( 'mediamora_antispam_last_report', $nu, false );
	} else {
		mediamora_antispam_plan_retry( 'mediamora_antispam_report', $nu, $s['email_interval'] );
	}
}

/**
 * Stuurt (indien ingeschakeld) maximaal 1x per ingestelde periode een
 * vangnet-mail met alle mogelijke twijfelgevallen sinds de vorige mail.
 * Draait volledig onafhankelijk van mediamora_antispam_maybe_send_report(),
 * dus ook als de gewone rapportmail op deze site uit staat.
 */
function mediamora_antispam_maybe_send_nearmiss_alert() {

	$s = mediamora_antispam_settings();

	if ( ! $s['nearmiss_alert_enabled'] ) {
		return;
	}

	$nu       = time();
	$last_run = (int) get_option( 'mediamora_antispam_last_nearmiss_alert', 0 );

	if ( ( $nu - $last_run ) < $s['nearmiss_email_interval'] ) {
		mediamora_antispam_plan_mail( 'mediamora_antispam_nearmiss_alert', 'mediamora_antispam_last_nearmiss_alert', $s['nearmiss_email_interval'] );
		return;
	}

	$nearmiss_file = mediamora_antispam_nearmiss_file();

	if ( ! file_exists( $nearmiss_file ) ) {
		return;
	}

	$lines = file( $nearmiss_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	if ( empty( $lines ) ) {
		return;
	}

	$cutoff_email     = mediamora_antispam_mail_window_start( $last_run, $nu, $s['nearmiss_email_interval'], $s['log_max_age'] );
	$cutoff_retention = $nu - $s['log_max_age'];

	$recent = array();
	$kept   = array();

	foreach ( $lines as $line ) {
		$timestamp = mediamora_antispam_parse_line_timestamp( $line );
		if ( false === $timestamp ) {
			continue;
		}
		if ( $timestamp >= $cutoff_retention ) {
			$kept[] = $line;
		}
		if ( $timestamp >= $cutoff_email && $timestamp < $nu ) {
			$recent[] = $line;
		}
	}

	file_put_contents(
		$nearmiss_file,
		implode( "\n", $kept ) . ( empty( $kept ) ? '' : "\n" ),
		LOCK_EX
	);

	if ( empty( $recent ) ) {
		update_option( 'mediamora_antispam_last_nearmiss_alert', $nu, false );
		return;
	}

	$count = count( $recent );
	$site  = wp_parse_url( home_url(), PHP_URL_HOST );

	$body  = sprintf(
		"Let op: %d veld(en) in je Elementor formulier(en) zaten verdacht dicht bij een weigeringsdrempel, zonder dat dit specifieke veld zelf werd geweigerd.\n\n" .
		"Dit kan wijzen op spam die net niet werd gevangen (een mogelijk vals negatief), maar kan net zo goed een volkomen legitieme inzending zijn die toevallig ongewone kenmerken had. Dit is een heuristiek, geen harde constatering, dus check dit met een kritische blik.\n\n" .
		"Let op het onderdeel \"inzending:\" per regel hieronder: dat geeft alleen aan of een ANDER veld door ONZE eigen checks alsnog is geweigerd. Als dat niet zo was, betekent dat NIET automatisch dat de inzending ook echt in je Elementor Inzendingen-lijst staat: Elementor Pro heeft een eigen, aparte honeypot-bescherming die volledig los van deze plugin kan ingrijpen, en dat kunnen wij niet zien of loggen. Check dus in twijfelgevallen altijd zelf even in Elementor of de inzending er daadwerkelijk staat.\n\n",
		$count
	);
	$body .= "Details:\n\n";
	$body .= implode( "\n", array_map( 'mediamora_antispam_line_for_mail', $recent ) );
	$body .= "\n\nDit vangnet draait onafhankelijk van de gewone rapportage, en blijft dus actief ook als je die op deze site hebt uitgezet.";

	$verstuurd = wp_mail(
		$s['alert_email'],
		sprintf( '[%s] Mogelijk gemiste spam: %d twijfelgeval(len)', $site, $count ),
		$body
	);

	// Zie de rapportmail: pas opslaan als de mail weg is.
	if ( $verstuurd ) {
		update_option( 'mediamora_antispam_last_nearmiss_alert', $nu, false );
	} else {
		mediamora_antispam_plan_retry( 'mediamora_antispam_nearmiss_alert', $nu, $s['nearmiss_email_interval'] );
	}
}
