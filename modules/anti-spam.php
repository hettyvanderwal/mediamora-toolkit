<?php
/**
 * Mediamora Toolkit, module: Anti-spam voor Elementor Forms
 * Overgenomen uit mediamora-anti-spam-elementor 5.4. De uitgebreide versiegeschiedenis staat in die repo. Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =====================================================================
// PADEN — afgeleide bestandslocaties (geen "instelling", niet in het
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
// INSTELLINGEN — opgeslagen in de database (get_option/update_option),
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
	);
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
 * @param array $post
 * @return array
 */
function mediamora_antispam_sanitize_and_save_settings( $post ) {

	$defaults = mediamora_antispam_default_settings();
	$clean    = array();

	$clean['alert_email']    = isset( $post['alert_email'] ) ? sanitize_email( $post['alert_email'] ) : $defaults['alert_email'];
	$clean['report_enabled'] = ! empty( $post['report_enabled'] );
	$clean['email_interval'] = max( 1, (int) ( $post['email_interval_hours'] ?? 24 ) ) * HOUR_IN_SECONDS;
	$clean['log_max_age']    = max( 1, (int) ( $post['log_max_age_days'] ?? 30 ) ) * DAY_IN_SECONDS;

	$clean['debug']                    = ! empty( $post['debug'] );
	$clean['reject_non_latin_text']    = ! empty( $post['reject_non_latin_text'] );
	$clean['reject_own_domain_mention'] = ! empty( $post['reject_own_domain_mention'] );
	$clean['reject_shortened_urls']    = ! empty( $post['reject_shortened_urls'] );
	$clean['reject_scheduling_links']  = ! empty( $post['reject_scheduling_links'] );

	$clean['nearmiss_alert_enabled']  = ! empty( $post['nearmiss_alert_enabled'] );
	$clean['nearmiss_email_interval'] = max( 1, (int) ( $post['nearmiss_email_interval_hours'] ?? 24 ) ) * HOUR_IN_SECONDS;

	$clean['gibberish_min_length']       = max( 1, (int) ( $post['gibberish_min_length'] ?? 8 ) );
	$clean['gibberish_vowel_ratio']      = min( 1, max( 0, (float) ( $post['gibberish_vowel_ratio'] ?? 0.25 ) ) );
	$clean['gibberish_transitions']      = max( 1, (int) ( $post['gibberish_transitions'] ?? 4 ) );
	$clean['gibberish_structure_length'] = max( 1, (int) ( $post['gibberish_structure_length'] ?? 12 ) );
	$clean['gibberish_threshold']        = max( 1, min( 3, (int) ( $post['gibberish_threshold'] ?? 2 ) ) );

	$clean['max_links_textarea'] = max( 0, (int) ( $post['max_links_textarea'] ?? 2 ) );

	$clean['duplicate_min_length'] = max( 1, (int) ( $post['duplicate_min_length'] ?? 50 ) );

	$clean['non_latin_min_letters'] = max( 1, (int) ( $post['non_latin_min_letters'] ?? 15 ) );
	$clean['non_latin_ratio']       = min( 1, max( 0, (float) ( $post['non_latin_ratio'] ?? 0.5 ) ) );
	$clean['non_latin_min_count']   = max( 1, (int) ( $post['non_latin_min_count'] ?? 3 ) );

	$clean['repetition_min_words']       = max( 1, (int) ( $post['repetition_min_words'] ?? 10 ) );
	$clean['repetition_min_word_length'] = max( 1, (int) ( $post['repetition_min_word_length'] ?? 4 ) );
	$clean['repetition_min_count']       = max( 1, (int) ( $post['repetition_min_count'] ?? 4 ) );
	$clean['repetition_max_ratio']       = min( 1, max( 0, (float) ( $post['repetition_max_ratio'] ?? 0.15 ) ) );

	update_option( 'mediamora_antispam_settings', $clean, false );

	return wp_parse_args( $clean, $defaults );
}

function mediamora_antispam_render_settings_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved = false;

	if ( isset( $_POST['mediamora_antispam_save'] ) && check_admin_referer( 'mediamora_antispam_save_settings' ) ) {
		$s     = mediamora_antispam_sanitize_and_save_settings( wp_unslash( $_POST ) );
		$saved = true;
	} else {
		$s = mediamora_antispam_settings();
	}
	?>
	<div class="wrap">
		<h1>Mediamora Anti-Spam voor Elementor Forms</h1>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>
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
						<p class="description">Alleen tijdelijk aanzetten om te troubleshooten, hierna weer uitzetten.</p>
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
		'recaptcha',
		'recaptcha_v3',
		'honeypot',
	);

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

		if ( '' === $value || in_array( $type, $skip_types, true ) ) {
			if ( $s['debug'] ) {
				mediamora_antispam_debug_log( $id, $type, $value, false, null, 'overgeslagen (leeg of skip-type)' );
			}
			continue;
		}

		$is_link         = mediamora_has_link_injection( $value, $type );
		$gibberish_score = mediamora_gibberish_score( $value );
		$is_gibberish    = ! $is_link && $gibberish_score['is_gibberish'];

		if ( $s['debug'] ) {
			mediamora_antispam_debug_log( $id, $type, $value, $is_link, $gibberish_score, 'beoordeeld' );
		}

		// Check 1: link injection (BBCode, of te veel URLs voor het veldtype)
		if ( $is_link ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'verdachte link', $id, $type, $value );
			$submission_rejected = true;
			continue; // dit veld is al afgekeurd, geen zin om ook nog op gibberish te checken
		}

		// Check 1b: niet-Latijns schrift, ook zonder link (alleen als expliciet ingeschakeld)
		if ( $s['reject_non_latin_text'] && mediamora_is_mostly_non_latin( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'niet-Latijns schrift', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1c: bericht noemt de domeinnaam van de site zelf (bulk-outreach patroon)
		if ( $s['reject_own_domain_mention'] && mediamora_mentions_own_domain( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'noemt eigen domeinnaam', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1d: verkorte URL-services (bit.ly, tinyurl, psee.io, enz.)
		if ( $s['reject_shortened_urls'] && mediamora_contains_shortened_url( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'verkorte URL', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 1e: afspraken-boekingslink (Calendly, Cal.com, enz. — cold-outreach patroon)
		if ( $s['reject_scheduling_links'] && mediamora_contains_scheduling_link( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'boekingslink', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 2: automatisch gegenereerde ("random") tekst
		if ( $is_gibberish ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
			mediamora_antispam_log( 'onleesbare tekst', $id, $type, $value );
			$submission_rejected = true;
			continue;
		}

		// Check 2b: overmatige woordherhaling (keyword stuffing)
		if ( mediamora_has_excessive_repetition( $value ) ) {
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
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
			$near_miss_reasons = mediamora_antispam_find_near_misses( $value, $type, $gibberish_score );
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
			$ajax_handler->add_error( $id, __( 'Ongeldige invoer gedetecteerd.', 'mediamora' ) );
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
		'x\.co',
		'shorturl\.at',
		'1url\.com',
		'rlnk\.co',
		'u\.to',
		'v\.gd',
		'short2url\.com',
		'clickto\.cc',
		'telegra\.ph',
	);

	$pattern = '/(' . implode( '|', $shorteners ) . ')/i';

	return (bool) preg_match( $pattern, $value );
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

	$pattern = '/(' . implode( '|', $schedulers ) . ')/i';

	return (bool) preg_match( $pattern, $value );
}

/**
 * Herkent link injection. BBCode [url=] is in geen enkel veld legitiem.
 * Kale domeinnamen (bijvoorbeeld "voorbeeld.ru" zonder "http://" ervoor)
 * worden hetzelfde behandeld als volledige URLs, want spam-bots laten het
 * protocol vaak expres weg om linkherkenning te omzeilen.
 *
 * Voor het aantal toegestane links geldt een drempel per veldtype: korte
 * velden zoals naam/telefoon horen nooit een link te bevatten, terwijl een
 * berichtveld best 1-2 legitieme links kan bevatten.
 *
 * Uitzondering op die drempel: een link gecombineerd met overwegend
 * niet-Latijns schrift wordt altijd geweigerd, ongeacht het aantal.
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

	$protocol_urls = preg_match_all( '/https?:\/\/\S+/i', $value );

	// Gevonden volledige URLs uit de tekst halen, zodat een domein daarbinnen
	// niet nog een keer meetelt bij de kale-domeinen check hieronder.
	$value_without_urls = preg_replace( '/https?:\/\/\S+/i', ' ', $value );

	$bare_domains = preg_match_all(
		'/\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:ru|su|com|net|org|info|biz|nl|be|de|fr|eu|io|co|me|shop|online|site|xyz|top|club|store|website|space|pro|vip|icu|cn|ua|by|kz|pl|it|es|uk|us)\b/i',
		$value_without_urls
	);

	$url_count = $protocol_urls + $bare_domains;

	if ( $url_count < 1 ) {
		return false;
	}

	if ( mediamora_is_mostly_non_latin( $value ) ) {
		return true;
	}

	if ( 'textarea' === $type ) {
		return $url_count > $s['max_links_textarea'];
	}

	return $url_count >= 1;
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
 * @return string[] Lijst met redenen, leeg als er niets opvallends is.
 */
function mediamora_antispam_find_near_misses( $value, $type, $gibberish_score ) {

	$s       = mediamora_antispam_settings();
	$reasons = array();

	// Gibberish: precies 1 signaal te weinig om te weigeren.
	if ( $gibberish_score['suspicious'] > 0 && $gibberish_score['suspicious'] === ( $s['gibberish_threshold'] - 1 ) ) {
		$reasons[] = 'onleesbare tekst (net niet genoeg signalen)';
	}

	// Links: exact op de toegestane grens voor berichtvelden.
	if ( 'textarea' === $type ) {
		$protocol_urls       = preg_match_all( '/https?:\/\/\S+/i', $value );
		$value_without_urls  = preg_replace( '/https?:\/\/\S+/i', ' ', $value );
		$bare_domains        = preg_match_all(
			'/\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:ru|su|com|net|org|info|biz|nl|be|de|fr|eu|io|co|me|shop|online|site|xyz|top|club|store|website|space|pro|vip|icu|cn|ua|by|kz|pl|it|es|uk|us)\b/i',
			$value_without_urls
		);
		$url_count = $protocol_urls + $bare_domains;
		if ( $url_count === (int) $s['max_links_textarea'] ) {
			$reasons[] = 'links (precies op de toegestane grens)';
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
		gmdate( 'Y-m-d H:i:s' ),
		$status,
		$field_id,
		$field_type,
		$snippet,
		$is_link ? 'ja' : 'nee',
		$score_text
	);

	file_put_contents( $debug_file, $line . "\n", FILE_APPEND | LOCK_EX );
}

/**
 * Schrijft een regel naar het logbestand en triggert (indien nodig) de
 * rapportmail.
 *
 * @param string $reason
 * @param string $field_id
 * @param string $field_type
 * @param string $value
 */
function mediamora_antispam_log( $reason, $field_id, $field_type, $value ) {

	mediamora_antispam_ensure_log_dir();

	$snippet = str_replace( array( "\r", "\n" ), ' ', $value );
	$snippet = mb_substr( $snippet, 0, 50 );

	$line = sprintf(
		'%s | veld: %s (%s) | reden: %s | waarde: %s',
		gmdate( 'Y-m-d H:i:s' ),
		$field_id,
		$field_type,
		$reason,
		$snippet
	);

	file_put_contents( mediamora_antispam_log_file(), $line . "\n", FILE_APPEND | LOCK_EX );

	mediamora_antispam_maybe_send_report();
}

/**
 * Schrijft een regel naar het vangnet-logbestand en triggert (indien
 * nodig) de vangnet-mail.
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
	$snippet = mb_substr( $snippet, 0, 80 );

	$line = sprintf(
		'%s | veld: %s (%s) | vermoedelijke reden(en): %s | inzending: %s | waarde: %s',
		gmdate( 'Y-m-d H:i:s' ),
		$field_id,
		$field_type,
		implode( ', ', $reasons ),
		$submission_status,
		$snippet
	);

	file_put_contents( mediamora_antispam_nearmiss_file(), $line . "\n", FILE_APPEND | LOCK_EX );

	mediamora_antispam_maybe_send_nearmiss_alert();
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

	// Ook herschrijven als er al een ander .htaccess ligt. Sites die op een oudere
	// versie zijn begonnen hebben de kale, foutgevoelige variant staan, en die wordt
	// zonder deze vergelijking nooit vervangen.
	if ( ! file_exists( $htaccess ) || file_get_contents( $htaccess ) !== $regels ) {
		file_put_contents( $htaccess, $regels, LOCK_EX );
	}

	// Vangnet voor servers die .htaccess negeren maar wel een directory-index tonen:
	// een lege index.php levert dan een blanco pagina in plaats van de bestandenlijst.
	// Beschermt niet tegen het rechtstreeks opvragen van log.txt zelf.
	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
	}
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
 * Haalt de timestamp uit een logregel (begint met "Y-m-d H:i:s | ...").
 *
 * @param string $line
 * @return int|false
 */
function mediamora_antispam_parse_line_timestamp( $line ) {

	$parts = explode( ' | ', $line, 2 );

	if ( empty( $parts[0] ) ) {
		return false;
	}

	$timestamp = strtotime( $parts[0] . ' UTC' );

	return false !== $timestamp ? $timestamp : false;
}

/**
 * Stuurt (indien ingeschakeld) maximaal 1x per ingestelde periode een
 * samenvattend rapport met ALLE geweigerde inzendingen sinds de vorige
 * mail, en ruimt tegelijk logregels op die ouder zijn dan de bewaartermijn.
 * De opschoning gebeurt altijd, ook als de rapportmail zelf uit staat.
 */
function mediamora_antispam_maybe_send_report() {

	$s = mediamora_antispam_settings();

	$last_run = (int) get_option( 'mediamora_antispam_last_report', 0 );

	if ( ( time() - $last_run ) < $s['email_interval'] ) {
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

	$cutoff_email     = time() - $s['email_interval'];
	$cutoff_retention = time() - $s['log_max_age'];

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

		if ( $timestamp >= $cutoff_email ) {
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

	update_option( 'mediamora_antispam_last_report', time(), false );

	if ( ! $s['report_enabled'] || empty( $recent_for_email ) ) {
		return;
	}

	$count = count( $recent_for_email );
	$site  = wp_parse_url( home_url(), PHP_URL_HOST );

	$body  = sprintf(
		"De anti-spam check op je Elementor formulier(en) heeft de afgelopen periode %d inzending(en) geweigerd.\n\n",
		$count
	);
	$body .= "Details:\n\n";
	$body .= implode( "\n", $recent_for_email );
	$body .= "\n\nVolledige log (incl. oudere, nog niet gerapporteerde regels binnen de bewaartermijn) staat in " . mediamora_antispam_log_path_label( $log_file ) . " op de server. Die mapnaam verschilt per site.";

	wp_mail(
		$s['alert_email'],
		sprintf( '[%s] Anti-spam rapport: %d geweigerde inzending(en)', $site, $count ),
		$body
	);
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

	$last_run = (int) get_option( 'mediamora_antispam_last_nearmiss_alert', 0 );

	if ( ( time() - $last_run ) < $s['nearmiss_email_interval'] ) {
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

	$cutoff_email     = time() - $s['nearmiss_email_interval'];
	$cutoff_retention = time() - $s['log_max_age'];

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
		if ( $timestamp >= $cutoff_email ) {
			$recent[] = $line;
		}
	}

	file_put_contents(
		$nearmiss_file,
		implode( "\n", $kept ) . ( empty( $kept ) ? '' : "\n" ),
		LOCK_EX
	);

	update_option( 'mediamora_antispam_last_nearmiss_alert', time(), false );

	if ( empty( $recent ) ) {
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
	$body .= implode( "\n", $recent );
	$body .= "\n\nDit vangnet draait onafhankelijk van de gewone rapportage, en blijft dus actief ook als je die op deze site hebt uitgezet.";

	wp_mail(
		$s['alert_email'],
		sprintf( '[%s] Mogelijk gemiste spam: %d twijfelgeval(len)', $site, $count ),
		$body
	);
}
