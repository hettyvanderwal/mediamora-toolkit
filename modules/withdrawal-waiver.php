<?php
/**
 * Mediamora Toolkit, module: Herroepingsrecht bij afrekenen
 * Wordt alleen geladen als de module aanstaat en WooCommerce actief is.
 *
 * Sinds 19 juni 2026 moet een webwinkel voor EU-consumenten een
 * herroepingsknop hebben, maar alleen bij bestellingen met een wettelijk
 * herroepingsrecht. Bij een online cursus met directe toegang vervalt dat
 * recht als de koper vóór de aankoop uitdrukkelijk instemt met directe
 * levering en verklaart daarmee zijn herroepingsrecht te verliezen, en de
 * verkoper dat bevestigt op een duurzame drager. Deze module zet daarvoor
 * een verplicht vinkje boven de bestelknop, bewaart de letterlijke tekst op
 * de bestelling en toont die in de bestelmail en in het beheer.
 *
 * Alleen de klassieke checkout, waar ook de Checkout-widget van Elementor
 * op draait. Het checkoutblok roept deze hooks niet aan.
 *
 * Oude variant: op sommige sites staat dit nog in functions.php, herkenbaar
 * aan de functie mm_withdrawal_text. Die laadt pas na de plugins, dus er
 * wordt pas op init gekeken. Staat die code er, dan hangt deze module geen
 * enkele checkout-, validatie-, opslag-, mail- of beheerhook op, anders
 * komt alles dubbel. Zodra de oude code weg is, neemt de module het over.
 *
 * De functies heten bewust mm_herroeping_*, zodat ze niet kunnen botsen met
 * de functies uit de oude functions.php-code.
 *
 * Bestellingen alleen via de order-API, dus werkt met en zonder HPOS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metakey op de bestelling. Staat al in bestaande bestellingen, dus niet
 * veranderen. Wordt nooit opgeruimd: het is het bewijs van de afstand.
 */
const MM_HERROEPING_ORDER_META = '_mm_withdrawal_waiver';

/** Metakey op het product: 'yes' als het product meetelt. */
const MM_HERROEPING_PRODUCT_META = '_mm_withdrawal_waiver_applies';

/** Naam en id van het vinkje in het afrekenformulier. */
const MM_HERROEPING_VELD = 'mm_withdrawal_waiver';

const MM_HERROEPING_OPTIE = 'mm_withdrawal_waiver_settings';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MM_TOOLKIT_BESTAND, true );
		}
	}
);


/* -------------------------------------------------------------------------
 * Instellingen en teksten
 * ---------------------------------------------------------------------- */

function mm_herroeping_instellingen() {
	$opgeslagen = get_option( MM_HERROEPING_OPTIE, array() );
	$opgeslagen = is_array( $opgeslagen ) ? $opgeslagen : array();

	$instellingen = wp_parse_args(
		$opgeslagen,
		array(
			'bereik'       => 'alle',
			'categorieen'  => array(),
			'tekst_vinkje' => '',
			'tekst_fout'   => '',
			'tekst_label'  => '',
		)
	);

	$instellingen['categorieen'] = array_values( array_filter( array_map( 'absint', (array) $instellingen['categorieen'] ) ) );

	return $instellingen;
}

/**
 * Standaardtekst op basis van de taal van de site: Nederlands voor nl_*,
 * anders Engels.
 *
 * @param string $soort vinkje, fout of label.
 */
function mm_herroeping_standaardtekst( $soort ) {

	$teksten = array(
		'nl' => array(
			'vinkje' => 'Ik wil direct toegang tot de cursus en weet dat ik daarmee afstand doe van mijn herroepingsrecht.',
			'fout'   => 'Kruis aan dat je direct toegang wilt tot de cursus en afziet van je herroepingsrecht.',
			'label'  => 'Herroepingsrecht',
		),
		'en' => array(
			'vinkje' => 'I want immediate access to the course and acknowledge that I thereby lose my right of withdrawal.',
			'fout'   => 'Please confirm that you want immediate access to the course and waive your right of withdrawal.',
			'label'  => 'Right of withdrawal',
		),
	);

	$taal = 0 === strpos( get_locale(), 'nl_' ) ? 'nl' : 'en';

	return $teksten[ $taal ][ $soort ];
}

/**
 * De tekst zoals hij getoond wordt: uit de instellingen, of de standaard
 * als het veld leeg is.
 *
 * @param string $soort vinkje, fout of label.
 */
function mm_herroeping_tekst( $soort ) {
	$instellingen = mm_herroeping_instellingen();
	$tekst        = trim( (string) $instellingen[ 'tekst_' . $soort ] );
	return '' !== $tekst ? $tekst : mm_herroeping_standaardtekst( $soort );
}

/**
 * Staat de oude functions.php-code er nog? Pas betrouwbaar vanaf init.
 */
function mm_herroeping_oude_code() {
	return function_exists( 'mm_withdrawal_text' );
}

/**
 * Gebruikt de afrekenpagina het checkoutblok?
 */
function mm_herroeping_blokcheckout() {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return false;
	}
	$pagina = wc_get_page_id( 'checkout' );
	return $pagina > 0 && has_block( 'woocommerce/checkout', $pagina );
}

/**
 * Meldingen voor de instellingenschermen.
 *
 * @return string[]
 */
function mm_herroeping_meldingen() {
	$meldingen = array();
	if ( mm_herroeping_oude_code() ) {
		$meldingen[] = 'Oude herroepingscode in functions.php gevonden (mm_withdrawal_text). De module doet niets tot die code verwijderd is.';
	}
	if ( mm_herroeping_blokcheckout() ) {
		$meldingen[] = 'De afrekenpagina gebruikt het checkoutblok. Deze module werkt alleen met de klassieke checkout of de Checkout-widget van Elementor.';
	}
	return $meldingen;
}


/* -------------------------------------------------------------------------
 * Bereik: welke producten tellen mee
 * ---------------------------------------------------------------------- */

/**
 * Telt dit product mee bij "Alleen gemarkeerde producten"? Verwacht het
 * hoofdproduct, niet een variatie.
 *
 * @param int   $product_id
 * @param int[] $categorieen Gekozen categorieën met hun subcategorieën.
 */
function mm_herroeping_product_telt( $product_id, $categorieen ) {

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return false;
	}

	if ( 'yes' === $product->get_meta( MM_HERROEPING_PRODUCT_META ) ) {
		return true;
	}

	return $categorieen && has_term( $categorieen, 'product_cat', $product_id );
}

/**
 * Moet het vinkje er bij deze winkelmand staan? Eén check voor tonen,
 * valideren en opslaan, zodat die drie nooit uit elkaar lopen.
 */
function mm_herroeping_nodig() {

	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return false;
	}

	$instellingen = mm_herroeping_instellingen();

	if ( 'gemarkeerd' !== $instellingen['bereik'] ) {
		return true;
	}

	// Een subcategorie valt ook onder de gekozen categorie.
	$categorieen = $instellingen['categorieen'];
	foreach ( $instellingen['categorieen'] as $categorie ) {
		$kinderen = get_term_children( $categorie, 'product_cat' );
		if ( is_array( $kinderen ) ) {
			$categorieen = array_merge( $categorieen, $kinderen );
		}
	}
	$categorieen = array_values( array_unique( array_map( 'absint', $categorieen ) ) );

	foreach ( WC()->cart->get_cart() as $regel ) {
		// Bij een variatie is product_id het hoofdproduct.
		if ( ! empty( $regel['product_id'] ) && mm_herroeping_product_telt( (int) $regel['product_id'], $categorieen ) ) {
			return true;
		}
	}

	return false;
}


/* -------------------------------------------------------------------------
 * Afrekenen, bestelling, mail en beheer
 *
 * Pas op init, want dan is functions.php geladen en weten we of de oude
 * code er staat.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mm_herroeping_hooks', 0 );

function mm_herroeping_hooks() {

	if ( mm_herroeping_oude_code() ) {
		return;
	}

	add_action( 'woocommerce_review_order_before_submit', 'mm_herroeping_vinkje' );
	add_action( 'woocommerce_after_checkout_validation', 'mm_herroeping_valideer', 10, 2 );
	add_action( 'woocommerce_checkout_create_order', 'mm_herroeping_opslaan', 10, 2 );
	add_filter( 'woocommerce_email_order_meta_fields', 'mm_herroeping_mail', 10, 3 );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'mm_herroeping_beheer' );
	add_action( 'wp_enqueue_scripts', 'mm_herroeping_script', 20 );
}

/**
 * Het vinkje, met dezelfde markup als het voorwaardenvinkje van
 * WooCommerce (checkout/terms.php), zodat thema en Elementor het net zo
 * opmaken. Nooit vooraf aangevinkt; het script hieronder zet alleen terug
 * wat de koper zelf had aangevinkt.
 */
function mm_herroeping_vinkje() {

	if ( ! mm_herroeping_nodig() ) {
		return;
	}
	?>
	<p class="form-row validate-required mm-withdrawal-waiver">
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="<?php echo esc_attr( MM_HERROEPING_VELD ); ?>" id="<?php echo esc_attr( MM_HERROEPING_VELD ); ?>" value="1" />
			<span class="mm-withdrawal-waiver-text"><?php echo esc_html( mm_herroeping_tekst( 'vinkje' ) ); ?></span>&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'woocommerce' ); ?>">*</abbr>
		</label>
	</p>
	<?php
}

/**
 * Net als WooCommerce het voor 'terms' doet: fout met het veld-id erbij,
 * zodat de melding en het markeren van het veld hetzelfde werken.
 *
 * @param array    $data
 * @param WP_Error $errors
 */
function mm_herroeping_valideer( $data, $errors ) {

	if ( ! empty( $data['woocommerce_checkout_update_totals'] ) || ! mm_herroeping_nodig() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() controleert de nonce.
	if ( empty( $_POST[ MM_HERROEPING_VELD ] ) ) {
		$errors->add( MM_HERROEPING_VELD, mm_herroeping_tekst( 'fout' ), array( 'id' => MM_HERROEPING_VELD ) );
	}
}

/**
 * Bewaart de letterlijke tekst zoals de koper hem zag.
 *
 * @param WC_Order $order
 * @param array    $data
 */
function mm_herroeping_opslaan( $order, $data ) {

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() controleert de nonce.
	if ( ! mm_herroeping_nodig() || empty( $_POST[ MM_HERROEPING_VELD ] ) ) {
		return;
	}

	$order->update_meta_data( MM_HERROEPING_ORDER_META, mm_herroeping_tekst( 'vinkje' ) );
}

/**
 * Regel in de bestelmails. Het label komt uit de instellingen, de waarde
 * is altijd de opgeslagen tekst.
 *
 * @param array    $velden
 * @param bool     $naar_beheerder
 * @param WC_Order $order
 */
function mm_herroeping_mail( $velden, $naar_beheerder, $order ) {

	if ( ! $order instanceof WC_Order ) {
		return $velden;
	}

	$waarde = (string) $order->get_meta( MM_HERROEPING_ORDER_META );
	if ( '' === $waarde ) {
		return $velden;
	}

	$velden[ MM_HERROEPING_VELD ] = array(
		'label' => mm_herroeping_tekst( 'label' ),
		'value' => $waarde,
	);

	return $velden;
}

/**
 * Regel onder het factuuradres op de bestelling in het beheer.
 *
 * @param WC_Order $order
 */
function mm_herroeping_beheer( $order ) {

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$waarde = (string) $order->get_meta( MM_HERROEPING_ORDER_META );
	if ( '' === $waarde ) {
		return;
	}

	echo '<p class="mm-withdrawal-waiver"><strong>' . esc_html( mm_herroeping_tekst( 'label' ) ) . ':</strong><br>' . esc_html( $waarde ) . '</p>';
}

/**
 * Bij het wijzigen van bijvoorbeeld het land ververst WooCommerce het blok
 * met de bestelknop, en daarmee het vinkje. Dit zet terug wat de koper had
 * aangevinkt. De stand wordt bij elke klik bijgehouden, zodat ook een klik
 * tijdens het verversen meetelt.
 */
function mm_herroeping_script() {

	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
		return;
	}

	$script = <<<'JS'
jQuery( function ( $ ) {
	var veld = '#mm_withdrawal_waiver';
	var aan = false;
	$( document.body ).on( 'change', veld, function () {
		aan = $( this ).is( ':checked' );
	} );
	$( document.body ).on( 'update_checkout', function () {
		if ( $( veld ).length ) {
			aan = $( veld ).is( ':checked' );
		}
	} );
	$( document.body ).on( 'updated_checkout', function () {
		if ( aan ) {
			$( veld ).prop( 'checked', true );
		}
	} );
} );
JS;

	wp_add_inline_script( 'wc-checkout', $script );
}


/* -------------------------------------------------------------------------
 * Productvinkje, tab Algemeen
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_product_options_general_product_data', 'mm_herroeping_productveld' );

function mm_herroeping_productveld() {
	echo '<div class="options_group">';
	woocommerce_wp_checkbox(
		array(
			'id'          => MM_HERROEPING_PRODUCT_META,
			'label'       => 'Herroepingsrecht',
			'description' => 'Bij afrekenen vragen om afstand van het herroepingsrecht (directe toegang). Telt alleen bij het bereik "Alleen gemarkeerde producten".',
		)
	);
	echo '</div>';
}

add_action( 'woocommerce_admin_process_product_object', 'mm_herroeping_productveld_opslaan' );

/**
 * @param WC_Product $product
 */
function mm_herroeping_productveld_opslaan( $product ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce controleert de nonce van het productscherm.
	if ( ! empty( $_POST[ MM_HERROEPING_PRODUCT_META ] ) ) {
		$product->update_meta_data( MM_HERROEPING_PRODUCT_META, 'yes' );
	} else {
		$product->delete_meta_data( MM_HERROEPING_PRODUCT_META );
	}
}


/* -------------------------------------------------------------------------
 * Instellingenscherm (Instellingen > Herroepingsrecht)
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'mm_herroeping_menu' );

function mm_herroeping_menu() {
	add_options_page( 'Herroepingsrecht bij afrekenen', 'Herroepingsrecht', 'manage_options', 'mm-herroeping', 'mm_herroeping_scherm' );
}

/**
 * @param array $post Ongeslashte POST-gegevens.
 */
function mm_herroeping_instellingen_opslaan( $post ) {

	$bestaand    = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	$bestaand    = is_array( $bestaand ) ? array_map( 'absint', $bestaand ) : array();
	$categorieen = isset( $post['categorieen'] ) ? array_map( 'absint', (array) $post['categorieen'] ) : array();

	$schoon = array(
		'bereik'       => isset( $post['bereik'] ) && 'gemarkeerd' === $post['bereik'] ? 'gemarkeerd' : 'alle',
		'categorieen'  => array_values( array_intersect( $categorieen, $bestaand ) ),
		'tekst_vinkje' => isset( $post['tekst_vinkje'] ) ? sanitize_text_field( $post['tekst_vinkje'] ) : '',
		'tekst_fout'   => isset( $post['tekst_fout'] ) ? sanitize_text_field( $post['tekst_fout'] ) : '',
		'tekst_label'  => isset( $post['tekst_label'] ) ? sanitize_text_field( $post['tekst_label'] ) : '',
	);

	update_option( MM_HERROEPING_OPTIE, $schoon, false );
}

function mm_herroeping_scherm() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$opgeslagen = false;
	if ( isset( $_POST['mm_herroeping_opslaan'] ) && check_admin_referer( 'mm_herroeping_opslaan' ) ) {
		mm_herroeping_instellingen_opslaan( wp_unslash( $_POST ) );
		$opgeslagen = true;
	}

	$s           = mm_herroeping_instellingen();
	$categorieen = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);
	$categorieen = is_array( $categorieen ) ? $categorieen : array();

	echo '<div class="wrap"><h1>Herroepingsrecht bij afrekenen</h1>';

	if ( $opgeslagen ) {
		echo '<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>';
	}
	foreach ( mm_herroeping_meldingen() as $melding ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $melding ) . '</p></div>';
	}

	echo '<p>Verplicht vinkje boven de bestelknop. De tekst zoals de koper hem zag komt op de bestelling, in de bestelmail en onder het factuuradres in het beheer.</p>';

	echo '<form method="post">';
	wp_nonce_field( 'mm_herroeping_opslaan' );

	echo '<h2>Bereik</h2><table class="form-table" role="presentation">';
	echo '<tr><th scope="row">Vinkje tonen bij</th><td><fieldset>';
	echo '<label><input type="radio" name="bereik" value="alle"' . checked( $s['bereik'], 'alle', false ) . '> Alle producten</label><br>';
	echo '<label><input type="radio" name="bereik" value="gemarkeerd"' . checked( $s['bereik'], 'gemarkeerd', false ) . '> Alleen gemarkeerde producten</label>';
	echo '<p class="description">Een product telt mee als het vinkje Herroepingsrecht in de tab Algemeen van het product aan staat, of als het in een van de categorieën hieronder valt (ook via een subcategorie). Bij een variatie telt het hoofdproduct. Het vinkje verschijnt als er minstens één meetellend product in de winkelmand zit.</p>';
	echo '</fieldset></td></tr>';

	echo '<tr><th scope="row">Categorieën</th><td><fieldset>';
	if ( ! $categorieen ) {
		echo '<p>Er zijn nog geen productcategorieën.</p>';
	} else {
		echo '<div style="max-height:260px;overflow:auto;border:1px solid #dcdcde;padding:8px 12px;max-width:520px;background:#fff;">';
		mm_herroeping_categorielijst( $categorieen, 0, 0, $s['categorieen'] );
		echo '</div>';
	}
	echo '<p class="description">Alleen van belang bij "Alleen gemarkeerde producten".</p>';
	echo '</fieldset></td></tr></table>';

	echo '<h2>Teksten</h2><p>Leeg laten geeft de standaardtekst in de taal van de site.</p><table class="form-table" role="presentation">';
	$velden = array(
		'vinkje' => 'Tekst bij het vinkje',
		'fout'   => 'Foutmelding',
		'label'  => 'Label in mail en beheer',
	);
	foreach ( $velden as $soort => $label ) {
		printf(
			'<tr><th scope="row"><label for="tekst_%1$s">%2$s</label></th><td><input type="text" class="large-text" id="tekst_%1$s" name="tekst_%1$s" value="%3$s" placeholder="%4$s"></td></tr>',
			esc_attr( $soort ),
			esc_html( $label ),
			esc_attr( $s[ 'tekst_' . $soort ] ),
			esc_attr( mm_herroeping_standaardtekst( $soort ) )
		);
	}
	echo '</table>';

	submit_button( 'Opslaan', 'primary', 'mm_herroeping_opslaan' );
	echo '</form></div>';
}

/**
 * Categorieën als ingesprongen vinkjeslijst.
 *
 * @param WP_Term[] $alle
 * @param int       $ouder
 * @param int       $diepte
 * @param int[]     $gekozen
 */
function mm_herroeping_categorielijst( $alle, $ouder, $diepte, $gekozen ) {
	foreach ( $alle as $term ) {
		if ( (int) $term->parent !== (int) $ouder ) {
			continue;
		}
		printf(
			// Padding en geen marge: WordPress zet de marge van labels in een
			// fieldset in .form-table vast met !important.
			'<label style="display:block;padding-left:%1$dpx;"><input type="checkbox" name="categorieen[]" value="%2$d"%3$s> %4$s</label>',
			(int) $diepte * 20,
			(int) $term->term_id,
			checked( in_array( (int) $term->term_id, $gekozen, true ), true, false ),
			esc_html( $term->name )
		);
		mm_herroeping_categorielijst( $alle, $term->term_id, $diepte + 1, $gekozen );
	}
}
