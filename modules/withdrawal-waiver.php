<?php
/**
 * Mediamora Toolkit, module: Herroepingsrecht bij afrekenen
 * Wordt alleen geladen als de module aanstaat en WooCommerce actief is.
 *
 * Sinds 19 juni 2026 moet een webwinkel voor EU-consumenten een
 * herroepingsknop hebben, maar alleen bij bestellingen met een wettelijk
 * herroepingsrecht. Deze module kent twee soorten producten:
 *
 * - digitaal: digitale content, zoals een online cursus met directe
 *   toegang. Het herroepingsrecht vervalt bij directe levering als de koper
 *   vooraf uitdrukkelijk instemt en verklaart zijn recht te verliezen.
 * - dienst: bijvoorbeeld healing, coaching of een consult. De koper stemt
 *   in met directe uitvoering; het recht vervalt pas als de dienst volledig
 *   is uitgevoerd. Tot dan blijft het herroepingsrecht, en daarmee de
 *   herroepingsknop, gewoon gelden.
 *
 * Per soort die in de winkelmand zit komt een verplicht vinkje boven de
 * bestelknop. De letterlijke tekst komt op de bestelling, in de bestelmail
 * en in het beheer.
 *
 * Welke soort een product heeft: de keuze op het product, anders de eerste
 * gekozen categorie (op naam), anders de standaardsoort. Zie
 * mm_herroeping_soort(); tonen, valideren, opslaan, mail en beheer gaan
 * allemaal via die ene functie.
 *
 * Alleen de klassieke checkout, waar ook de Checkout-widget van Elementor
 * op draait. Het checkoutblok roept deze hooks niet aan.
 *
 * Oude varianten: op sommige sites staat dit nog in functions.php, voor
 * digitaal herkenbaar aan de functie mm_withdrawal_text en voor dienst aan
 * mm_consent_text. Die laden pas na de plugins, dus er wordt pas op init
 * gekeken. Staat de oude code van een soort er, dan doet de module voor die
 * soort niets: geen vinkje, validatie, opslag, mail of beheer, anders komt
 * alles dubbel. De andere soort werkt gewoon.
 *
 * De functies heten bewust mm_herroeping_*, zodat ze niet kunnen botsen met
 * de functies uit de oude functions.php-code.
 *
 * Het omzetten van de instellingen van 1.6.0 en de gedeelde constanten
 * staan in includes/herroeping.php.
 *
 * Bestellingen alleen via de order-API, dus werkt met en zonder HPOS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MM_TOOLKIT_BESTAND, true );
		}
	}
);


/* -------------------------------------------------------------------------
 * Soorten, instellingen en teksten
 * ---------------------------------------------------------------------- */

/**
 * De twee soorten, in de volgorde waarin de vinkjes verschijnen.
 *
 * veld:  naam en id van het vinkje in het afrekenformulier.
 * meta:  metakey op de bestelling. Staan al in bestaande bestellingen (ook
 *        van de oude functions.php-code), dus niet veranderen. Worden nooit
 *        opgeruimd: het is het bewijs van de instemming.
 * oud:   functie van de oude functions.php-code.
 *
 * @return array[]
 */
function mm_herroeping_soorten() {
	return array(
		'digitaal' => array(
			'naam' => 'Digitale content',
			'veld' => 'mm_withdrawal_waiver',
			'meta' => '_mm_withdrawal_waiver',
			'oud'  => 'mm_withdrawal_text',
		),
		'dienst'   => array(
			'naam' => 'Dienst',
			'veld' => 'mm_service_consent',
			'meta' => '_mm_service_consent',
			'oud'  => 'mm_consent_text',
		),
	);
}

/** Tekstvelden per soort. */
function mm_herroeping_tekstvelden() {
	return array( 'vinkje', 'fout', 'label', 'herinnering' );
}

function mm_herroeping_instellingen() {

	// Voor het geval er vóór init al iets gelezen wordt.
	mm_herroeping_omzetten();

	$opgeslagen = get_option( MM_HERROEPING_OPTIE, array() );
	$opgeslagen = is_array( $opgeslagen ) ? $opgeslagen : array();

	$standaard = array(
		'versie'         => MM_HERROEPING_OPTIE_VERSIE,
		'standaardsoort' => 'digitaal',
	);
	foreach ( array_keys( mm_herroeping_soorten() ) as $soort ) {
		$standaard[ 'categorieen_' . $soort ] = array();
		foreach ( mm_herroeping_tekstvelden() as $veld ) {
			$standaard[ $soort . '_' . $veld ] = '';
		}
	}

	$instellingen = wp_parse_args( $opgeslagen, $standaard );

	if ( ! in_array( $instellingen['standaardsoort'], array( 'digitaal', 'dienst', 'geen' ), true ) ) {
		$instellingen['standaardsoort'] = 'digitaal';
	}
	foreach ( array_keys( mm_herroeping_soorten() ) as $soort ) {
		$instellingen[ 'categorieen_' . $soort ] = array_values( array_filter( array_map( 'absint', (array) $instellingen[ 'categorieen_' . $soort ] ) ) );
	}

	return $instellingen;
}

/**
 * Standaardtekst op basis van de taal van de site: Nederlands voor nl_*,
 * anders Engels.
 *
 * @param string $soort digitaal of dienst.
 * @param string $veld  vinkje, fout, label of herinnering.
 */
function mm_herroeping_standaardtekst( $soort, $veld ) {

	$teksten = array(
		'digitaal' => array(
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
		),
		'dienst'   => array(
			'nl' => array(
				'vinkje' => 'Ik wil dat de dienst direct wordt uitgevoerd en weet dat ik mijn herroepingsrecht verlies zodra de dienst volledig is uitgevoerd.',
				'fout'   => 'Geef aan dat je wilt dat de dienst direct wordt uitgevoerd.',
				'label'  => 'Herroepingsrecht',
			),
			'en' => array(
				'vinkje' => 'I want the service to be performed immediately and acknowledge that I lose my right of withdrawal once the service has been fully performed.',
				'fout'   => 'Please confirm that you want the service to be performed immediately.',
				'label'  => 'Right of withdrawal',
			),
		),
	);

	$taal = 0 === strpos( get_locale(), 'nl_' ) ? 'nl' : 'en';

	// De herinnering heeft geen standaardtekst.
	return isset( $teksten[ $soort ][ $taal ][ $veld ] ) ? $teksten[ $soort ][ $taal ][ $veld ] : '';
}

/**
 * De tekst zoals hij getoond wordt: uit de instellingen, of de standaard
 * als het veld leeg is.
 *
 * @param string $soort digitaal of dienst.
 * @param string $veld  vinkje, fout, label of herinnering.
 */
function mm_herroeping_tekst( $soort, $veld ) {
	$instellingen = mm_herroeping_instellingen();
	$tekst        = trim( (string) $instellingen[ $soort . '_' . $veld ] );
	return '' !== $tekst ? $tekst : mm_herroeping_standaardtekst( $soort, $veld );
}

/**
 * Staat de oude functions.php-code van deze soort er nog? Pas betrouwbaar
 * vanaf init.
 *
 * @param string $soort digitaal of dienst.
 */
function mm_herroeping_oude_code( $soort ) {
	$soorten = mm_herroeping_soorten();
	return function_exists( $soorten[ $soort ]['oud'] );
}

/**
 * De soorten waarvoor de module het werk doet: zonder oude code.
 *
 * @return array[] Zelfde opbouw als mm_herroeping_soorten().
 */
function mm_herroeping_actieve_soorten() {
	$actief = array();
	foreach ( mm_herroeping_soorten() as $soort => $gegevens ) {
		if ( ! mm_herroeping_oude_code( $soort ) ) {
			$actief[ $soort ] = $gegevens;
		}
	}
	return $actief;
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
 * Staat er iets op 'dienst': de standaardsoort, een categorie of een
 * product?
 */
function mm_herroeping_dienst_in_gebruik() {
	global $wpdb;

	$instellingen = mm_herroeping_instellingen();
	if ( 'dienst' === $instellingen['standaardsoort'] || $instellingen['categorieen_dienst'] ) {
		return true;
	}

	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s AND pm.meta_value = 'dienst'
			AND p.post_type = 'product' AND p.post_status NOT IN ( 'trash', 'auto-draft' )
			LIMIT 1",
			MM_HERROEPING_SOORT_META
		)
	);
}

/**
 * Meldingen voor de instellingenschermen.
 *
 * @return string[]
 */
function mm_herroeping_meldingen() {
	$meldingen = array();
	if ( mm_herroeping_oude_code( 'digitaal' ) ) {
		$meldingen[] = 'Oude herroepingscode in functions.php gevonden (mm_withdrawal_text). De module doet niets voor digitale content tot die code verwijderd is.';
	}
	if ( mm_herroeping_oude_code( 'dienst' ) ) {
		$meldingen[] = 'Oude code voor diensten in functions.php gevonden (mm_consent_text). De module doet niets voor diensten tot die code verwijderd is.';
	}
	if ( mm_herroeping_blokcheckout() ) {
		$meldingen[] = 'De afrekenpagina gebruikt het checkoutblok. Deze module werkt alleen met de klassieke checkout of de Checkout-widget van Elementor.';
	}
	if ( 'yes' !== get_option( 'woocommerce_feature_order_withdrawal_enabled' ) && mm_herroeping_dienst_in_gebruik() ) {
		$meldingen[] = 'Er staat iets op Dienst. Bij een dienst blijft het herroepingsrecht gelden tot de dienst volledig is uitgevoerd, dus de koper moet kunnen herroepen. Zet Order withdrawal van WooCommerce waarschijnlijk aan (WooCommerce > Instellingen > Geavanceerd > Features).';
	}
	return $meldingen;
}


/* -------------------------------------------------------------------------
 * Soortbepaling
 * ---------------------------------------------------------------------- */

/**
 * Gekozen categorieën met al hun subcategorieën.
 *
 * @param int[] $categorieen
 * @return int[]
 */
function mm_herroeping_met_subcategorieen( $categorieen ) {
	static $cache = array();

	$sleutel = implode( ',', $categorieen );
	if ( isset( $cache[ $sleutel ] ) ) {
		return $cache[ $sleutel ];
	}

	$alle = $categorieen;
	foreach ( $categorieen as $categorie ) {
		$kinderen = get_term_children( $categorie, 'product_cat' );
		if ( is_array( $kinderen ) ) {
			$alle = array_merge( $alle, $kinderen );
		}
	}

	$cache[ $sleutel ] = array_values( array_unique( array_map( 'absint', $alle ) ) );
	return $cache[ $sleutel ];
}

/**
 * Welke soort heeft dit product? De enige plek waar dat bepaald wordt.
 *
 * 1. De keuze op het product (tab Algemeen).
 * 2. De categorieën van het product, op naam (alfabetisch). De eerste die
 *    bij een soort hoort (ook via een bovenliggende categorie) bepaalt de
 *    soort. Hoort één categorie bij beide soorten, dan wint digitaal.
 * 3. De standaardsoort.
 *
 * Bij een variatie telt het hoofdproduct.
 *
 * @param int $product_id
 * @return string digitaal, dienst of geen.
 */
function mm_herroeping_soort( $product_id ) {

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return 'geen';
	}
	if ( $product->is_type( 'variation' ) ) {
		$product = wc_get_product( $product->get_parent_id() );
		if ( ! $product ) {
			return 'geen';
		}
	}

	$eigen = (string) $product->get_meta( MM_HERROEPING_SOORT_META );
	if ( in_array( $eigen, array( 'digitaal', 'dienst', 'geen' ), true ) ) {
		return $eigen;
	}

	$instellingen = mm_herroeping_instellingen();

	$per_soort = array();
	foreach ( array_keys( mm_herroeping_soorten() ) as $soort ) {
		if ( $instellingen[ 'categorieen_' . $soort ] ) {
			$per_soort[ $soort ] = mm_herroeping_met_subcategorieen( $instellingen[ 'categorieen_' . $soort ] );
		}
	}

	if ( $per_soort ) {
		$termen = get_the_terms( $product->get_id(), 'product_cat' );
		if ( is_array( $termen ) ) {
			// Zelf op naam sorteren: WooCommerce kan productcategorieën op de
			// eigen volgorde uit het beheer zetten.
			usort(
				$termen,
				function ( $a, $b ) {
					$verschil = strcasecmp( $a->name, $b->name );
					return 0 !== $verschil ? $verschil : (int) $a->term_id - (int) $b->term_id;
				}
			);
			foreach ( $termen as $term ) {
				foreach ( $per_soort as $soort => $ids ) {
					if ( in_array( (int) $term->term_id, $ids, true ) ) {
						return $soort;
					}
				}
			}
		}
	}

	return $instellingen['standaardsoort'];
}

/**
 * Welke soorten zitten in de winkelmand, in de volgorde van de vinkjes?
 * Alleen soorten zonder oude code. Eén check voor tonen, valideren en
 * opslaan, zodat die drie nooit uit elkaar lopen.
 *
 * @return string[]
 */
function mm_herroeping_soorten_in_winkelmand() {

	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return array();
	}

	$actief = mm_herroeping_actieve_soorten();
	if ( ! $actief ) {
		return array();
	}

	$gevonden = array();
	foreach ( WC()->cart->get_cart() as $regel ) {
		// Bij een variatie is product_id het hoofdproduct.
		if ( ! empty( $regel['product_id'] ) ) {
			$gevonden[ mm_herroeping_soort( (int) $regel['product_id'] ) ] = true;
		}
	}

	return array_values( array_filter( array_keys( $actief ), function ( $soort ) use ( $gevonden ) {
		return isset( $gevonden[ $soort ] );
	} ) );
}


/* -------------------------------------------------------------------------
 * Afrekenen, bestelling, mail en beheer
 *
 * Pas op init, want dan is functions.php geladen en weten we of de oude
 * code er staat. Staat van beide soorten de oude code er, dan komt er geen
 * enkele hook. Staat hij er van één soort, dan slaan de hooks die soort
 * over.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mm_herroeping_hooks', 0 );

function mm_herroeping_hooks() {

	if ( ! mm_herroeping_actieve_soorten() ) {
		return;
	}

	add_action( 'woocommerce_review_order_before_submit', 'mm_herroeping_vinkjes' );
	add_action( 'woocommerce_after_checkout_validation', 'mm_herroeping_valideer', 10, 2 );
	add_action( 'woocommerce_checkout_create_order', 'mm_herroeping_opslaan', 10, 2 );
	add_filter( 'woocommerce_email_order_meta_fields', 'mm_herroeping_mail', 10, 3 );
	add_action( 'woocommerce_email_footer', 'mm_herroeping_herinnering', 5 );
	add_action( 'woocommerce_email_customer_details', 'mm_herroeping_herinnering_tekst', 100, 4 );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'mm_herroeping_beheer' );
	add_action( 'wp_enqueue_scripts', 'mm_herroeping_script', 20 );
}

/**
 * De vinkjes, eerst digitaal en dan dienst, onder het voorwaardenvinkje en
 * boven de bestelknop. Dezelfde markup als het voorwaardenvinkje van
 * WooCommerce (checkout/terms.php), zodat thema en Elementor ze net zo
 * opmaken. Nooit vooraf aangevinkt; het script hieronder zet alleen terug
 * wat de koper zelf had aangevinkt.
 */
function mm_herroeping_vinkjes() {

	$soorten = mm_herroeping_soorten();

	foreach ( mm_herroeping_soorten_in_winkelmand() as $soort ) {
		$veld = $soorten[ $soort ]['veld'];
		?>
		<p class="form-row validate-required mm-withdrawal-waiver mm-withdrawal-<?php echo esc_attr( $soort ); ?>">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="<?php echo esc_attr( $veld ); ?>" id="<?php echo esc_attr( $veld ); ?>" value="1" />
				<span class="mm-withdrawal-waiver-text"><?php echo esc_html( mm_herroeping_tekst( $soort, 'vinkje' ) ); ?></span>&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'woocommerce' ); ?>">*</abbr>
			</label>
		</p>
		<?php
	}
}

/**
 * Net als WooCommerce het voor 'terms' doet: fout met het veld-id erbij,
 * zodat de melding en het markeren van het veld hetzelfde werken.
 *
 * @param array    $data
 * @param WP_Error $errors
 */
function mm_herroeping_valideer( $data, $errors ) {

	if ( ! empty( $data['woocommerce_checkout_update_totals'] ) ) {
		return;
	}

	$soorten = mm_herroeping_soorten();

	foreach ( mm_herroeping_soorten_in_winkelmand() as $soort ) {
		$veld = $soorten[ $soort ]['veld'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() controleert de nonce.
		if ( empty( $_POST[ $veld ] ) ) {
			$errors->add( $veld, mm_herroeping_tekst( $soort, 'fout' ), array( 'id' => $veld ) );
		}
	}
}

/**
 * Bewaart per soort de letterlijke tekst zoals de koper hem zag.
 *
 * @param WC_Order $order
 * @param array    $data
 */
function mm_herroeping_opslaan( $order, $data ) {

	$soorten = mm_herroeping_soorten();

	foreach ( mm_herroeping_soorten_in_winkelmand() as $soort ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() controleert de nonce.
		if ( ! empty( $_POST[ $soorten[ $soort ]['veld'] ] ) ) {
			$order->update_meta_data( $soorten[ $soort ]['meta'], mm_herroeping_tekst( $soort, 'vinkje' ) );
		}
	}
}

/**
 * De opgeslagen teksten van deze bestelling, per actieve soort.
 *
 * @param WC_Order $order
 * @return string[] Soort => opgeslagen tekst.
 */
function mm_herroeping_op_bestelling( $order ) {

	$gevonden = array();
	if ( ! $order instanceof WC_Order ) {
		return $gevonden;
	}

	foreach ( mm_herroeping_actieve_soorten() as $soort => $gegevens ) {
		$waarde = (string) $order->get_meta( $gegevens['meta'] );
		if ( '' !== $waarde ) {
			$gevonden[ $soort ] = $waarde;
		}
	}

	return $gevonden;
}

/**
 * Regels in de bestelmails. Het label komt uit de instellingen, de waarde
 * is altijd de opgeslagen tekst.
 *
 * @param array    $velden
 * @param bool     $naar_beheerder
 * @param WC_Order $order
 */
function mm_herroeping_mail( $velden, $naar_beheerder, $order ) {

	$soorten = mm_herroeping_soorten();

	foreach ( mm_herroeping_op_bestelling( $order ) as $soort => $waarde ) {
		$velden[ $soorten[ $soort ]['veld'] ] = array(
			'label' => mm_herroeping_tekst( $soort, 'label' ),
			'value' => $waarde,
		);
	}

	return $velden;
}

/**
 * De herinneringen voor deze mail: alleen "Bestelling in behandeling" en
 * alleen voor de soorten die op de bestelling staan en een ingevulde
 * herinnering hebben.
 *
 * @param WC_Email $email
 * @return string[]
 */
function mm_herroeping_herinneringen( $email ) {

	if ( ! is_object( $email ) || ! isset( $email->id ) || 'customer_processing_order' !== $email->id ) {
		return array();
	}

	$teksten = array();
	foreach ( array_keys( mm_herroeping_op_bestelling( $email->object ) ) as $soort ) {
		$tekst = mm_herroeping_tekst( $soort, 'herinnering' );
		if ( '' !== $tekst ) {
			$teksten[] = $tekst;
		}
	}

	return $teksten;
}

/**
 * Herinnering onderaan de HTML-mail, vóór de voettekst van WooCommerce
 * (die hangt op prioriteit 10). De platte-tekstmail roept deze hook niet
 * aan.
 *
 * @param WC_Email $email
 */
function mm_herroeping_herinnering( $email = null ) {

	if ( ! is_object( $email ) || 'plain' === $email->get_email_type() ) {
		return;
	}

	foreach ( mm_herroeping_herinneringen( $email ) as $tekst ) {
		echo '<p style="font-size:11px;line-height:1.5;color:#888888;margin:24px 0 0;">' . nl2br( esc_html( $tekst ) ) . '</p>';
	}
}

/**
 * Dezelfde herinnering in de platte-tekstmail, als losse alinea na de
 * klantgegevens.
 *
 * @param WC_Order $order
 * @param bool     $naar_beheerder
 * @param bool     $platte_tekst
 * @param WC_Email $email
 */
function mm_herroeping_herinnering_tekst( $order, $naar_beheerder = false, $platte_tekst = false, $email = null ) {

	if ( ! $platte_tekst ) {
		return;
	}

	foreach ( mm_herroeping_herinneringen( $email ) as $tekst ) {
		echo "\n" . esc_html( wp_strip_all_tags( $tekst ) ) . "\n";
	}
}

/**
 * Regels onder het factuuradres op de bestelling in het beheer.
 *
 * @param WC_Order $order
 */
function mm_herroeping_beheer( $order ) {
	foreach ( mm_herroeping_op_bestelling( $order ) as $soort => $waarde ) {
		echo '<p class="mm-withdrawal-waiver mm-withdrawal-' . esc_attr( $soort ) . '"><strong>' . esc_html( mm_herroeping_tekst( $soort, 'label' ) ) . ':</strong><br>' . esc_html( $waarde ) . '</p>';
	}
}

/**
 * Bij het wijzigen van bijvoorbeeld het land ververst WooCommerce het blok
 * met de bestelknop, en daarmee de vinkjes. Dit zet terug wat de koper had
 * aangevinkt. De stand wordt bij elke klik bijgehouden, zodat ook een klik
 * tijdens het verversen meetelt.
 *
 * Plus de rode rand om een vinkje dat niet is aangevinkt, zoals WooCommerce
 * die voor #terms geeft. Dat doet WooCommerce alleen voor zijn eigen veld.
 */
function mm_herroeping_script() {

	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
		return;
	}

	$velden = array();
	foreach ( mm_herroeping_actieve_soorten() as $gegevens ) {
		$velden[] = '#' . $gegevens['veld'];
	}

	$script = <<<'JS'
jQuery( function ( $ ) {
	var velden = %s;
	var aan = {};
	$.each( velden, function ( i, veld ) {
		$( document.body ).on( 'change', veld, function () {
			aan[ veld ] = $( this ).is( ':checked' );
		} );
	} );
	$( document.body ).on( 'update_checkout', function () {
		$.each( velden, function ( i, veld ) {
			if ( $( veld ).length ) {
				aan[ veld ] = $( veld ).is( ':checked' );
			}
		} );
	} );
	$( document.body ).on( 'updated_checkout', function () {
		$.each( velden, function ( i, veld ) {
			if ( aan[ veld ] ) {
				$( veld ).prop( 'checked', true );
			}
		} );
	} );
} );
JS;

	wp_add_inline_script( 'wc-checkout', sprintf( $script, wp_json_encode( $velden ) ) );

	$css = array();
	foreach ( $velden as $veld ) {
		$css[] = '.woocommerce-invalid ' . $veld;
	}

	wp_register_style( 'mm-herroeping', false, array(), MM_TOOLKIT_VERSIE );
	wp_enqueue_style( 'mm-herroeping' );
	wp_add_inline_style( 'mm-herroeping', implode( ',', $css ) . '{outline:2px solid var(--wc-red);outline-offset:2px;}' );
}


/* -------------------------------------------------------------------------
 * Keuzeveld op het product, tab Algemeen
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_product_options_general_product_data', 'mm_herroeping_productveld' );

function mm_herroeping_productveld() {

	$standaard = array(
		'digitaal' => 'digitale content',
		'dienst'   => 'dienst',
		'geen'     => 'geen vinkje',
	);
	$instellingen = mm_herroeping_instellingen();

	echo '<div class="options_group">';
	woocommerce_wp_select(
		array(
			'id'          => MM_HERROEPING_SOORT_META,
			'label'       => 'Herroepingsrecht',
			'options'     => array(
				''         => 'Standaard (categorie, anders ' . $standaard[ $instellingen['standaardsoort'] ] . ')',
				'digitaal' => 'Digitale content (direct toegang, recht vervalt)',
				'dienst'   => 'Dienst (direct uitvoeren, recht vervalt na uitvoering)',
				'geen'     => 'Geen vinkje',
			),
			'description' => 'Welk vinkje de koper bij afrekenen krijgt. Standaard volgt de categorie of de standaardsoort onder Instellingen > Herroepingsrecht.',
			'desc_tip'    => true,
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
	$waarde = isset( $_POST[ MM_HERROEPING_SOORT_META ] ) ? sanitize_key( wp_unslash( $_POST[ MM_HERROEPING_SOORT_META ] ) ) : '';

	if ( in_array( $waarde, array( 'digitaal', 'dienst', 'geen' ), true ) ) {
		$product->update_meta_data( MM_HERROEPING_SOORT_META, $waarde );
	} else {
		$product->delete_meta_data( MM_HERROEPING_SOORT_META );
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

	$bestaand = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	$bestaand = is_array( $bestaand ) ? array_map( 'absint', $bestaand ) : array();

	$schoon = array(
		'versie'         => MM_HERROEPING_OPTIE_VERSIE,
		'standaardsoort' => isset( $post['standaardsoort'] ) && in_array( $post['standaardsoort'], array( 'digitaal', 'dienst', 'geen' ), true ) ? $post['standaardsoort'] : 'digitaal',
	);

	foreach ( array_keys( mm_herroeping_soorten() ) as $soort ) {
		$categorieen                     = isset( $post[ 'categorieen_' . $soort ] ) ? array_map( 'absint', (array) $post[ 'categorieen_' . $soort ] ) : array();
		$schoon[ 'categorieen_' . $soort ] = array_values( array_intersect( $categorieen, $bestaand ) );

		foreach ( mm_herroeping_tekstvelden() as $veld ) {
			$sleutel = $soort . '_' . $veld;
			if ( ! isset( $post[ $sleutel ] ) ) {
				$schoon[ $sleutel ] = '';
			} elseif ( 'herinnering' === $veld ) {
				$schoon[ $sleutel ] = sanitize_textarea_field( $post[ $sleutel ] );
			} else {
				$schoon[ $sleutel ] = sanitize_text_field( $post[ $sleutel ] );
			}
		}
	}

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

	echo '<p>Per soort die in de winkelmand zit komt een verplicht vinkje boven de bestelknop: eerst digitale content, dan dienst. De tekst zoals de koper hem zag komt op de bestelling, in de bestelmail en onder het factuuradres in het beheer.</p>';
	echo '<ul style="list-style:disc;padding-left:20px;">';
	echo '<li><strong>Digitale content</strong>, zoals een online cursus: de koper stemt in met directe toegang en verliest daarmee zijn herroepingsrecht.</li>';
	echo '<li><strong>Dienst</strong>, zoals healing, coaching of een consult: de koper stemt in met directe uitvoering. Het herroepingsrecht vervalt pas als de dienst volledig is uitgevoerd; tot dan blijft het gelden.</li>';
	echo '</ul>';

	echo '<form method="post">';
	wp_nonce_field( 'mm_herroeping_opslaan' );

	echo '<h2>Soort per product</h2><table class="form-table" role="presentation">';
	echo '<tr><th scope="row">Standaardsoort</th><td><fieldset>';
	$keuzes = array(
		'digitaal' => 'Digitale content',
		'dienst'   => 'Dienst',
		'geen'     => 'Geen vinkje',
	);
	foreach ( $keuzes as $waarde => $label ) {
		echo '<label><input type="radio" name="standaardsoort" value="' . esc_attr( $waarde ) . '"' . checked( $s['standaardsoort'], $waarde, false ) . '> ' . esc_html( $label ) . '</label><br>';
	}
	echo '<p class="description">Volgorde: de keuze Herroepingsrecht in de tab Algemeen van het product gaat voor, dan de categorieën hieronder (ook via een subcategorie), dan deze standaardsoort. Staat een product in categorieën van beide soorten, dan geldt de soort van de eerste categorie op alfabetische volgorde. Bij een variatie telt het hoofdproduct.</p>';
	echo '</fieldset></td></tr>';

	foreach ( mm_herroeping_soorten() as $soort => $gegevens ) {
		echo '<tr><th scope="row">Categorieën ' . esc_html( strtolower( $gegevens['naam'] ) ) . '</th><td><fieldset>';
		if ( ! $categorieen ) {
			echo '<p>Er zijn nog geen productcategorieën.</p>';
		} else {
			echo '<div style="max-height:260px;overflow:auto;border:1px solid #dcdcde;padding:8px 12px;max-width:520px;background:#fff;">';
			mm_herroeping_categorielijst( $categorieen, 0, 0, $s[ 'categorieen_' . $soort ], 'categorieen_' . $soort );
			echo '</div>';
		}
		echo '</fieldset></td></tr>';
	}
	echo '</table>';

	$velden = array(
		'vinkje'      => 'Tekst bij het vinkje',
		'fout'        => 'Foutmelding',
		'label'       => 'Label in mail en beheer',
		'herinnering' => 'Herinnering in de mail',
	);

	foreach ( mm_herroeping_soorten() as $soort => $gegevens ) {

		echo '<h2>Teksten ' . esc_html( strtolower( $gegevens['naam'] ) ) . '</h2>';
		if ( mm_herroeping_oude_code( $soort ) ) {
			echo '<p><strong>Niet in gebruik: de oude code in functions.php (' . esc_html( $gegevens['oud'] ) . ') doet dit nog.</strong></p>';
		}
		echo '<p>Leeg laten geeft de standaardtekst in de taal van de site. De herinnering heeft geen standaardtekst: leeg is geen herinnering. Ingevuld staat hij in kleine grijze letters onderaan de mail "Bestelling in behandeling", alleen bij bestellingen met dit vinkje.</p>';
		echo '<table class="form-table" role="presentation">';

		foreach ( $velden as $veld => $label ) {
			$id = $soort . '_' . $veld;
			if ( 'herinnering' === $veld ) {
				printf(
					'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><textarea class="large-text" rows="3" id="%1$s" name="%1$s">%3$s</textarea></td></tr>',
					esc_attr( $id ),
					esc_html( $label ),
					esc_textarea( $s[ $id ] )
				);
			} else {
				printf(
					'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" class="large-text" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s"></td></tr>',
					esc_attr( $id ),
					esc_html( $label ),
					esc_attr( $s[ $id ] ),
					esc_attr( mm_herroeping_standaardtekst( $soort, $veld ) )
				);
			}
		}
		echo '</table>';
	}

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
 * @param string    $naam    Naam van het formulierveld, zonder [].
 */
function mm_herroeping_categorielijst( $alle, $ouder, $diepte, $gekozen, $naam ) {
	foreach ( $alle as $term ) {
		if ( (int) $term->parent !== (int) $ouder ) {
			continue;
		}
		printf(
			// Padding en geen marge: WordPress zet de marge van labels in een
			// fieldset in .form-table vast met !important.
			'<label style="display:block;padding-left:%1$dpx;"><input type="checkbox" name="%2$s[]" value="%3$d"%4$s> %5$s</label>',
			(int) $diepte * 20,
			esc_attr( $naam ),
			(int) $term->term_id,
			checked( in_array( (int) $term->term_id, $gekozen, true ), true, false ),
			esc_html( $term->name )
		);
		mm_herroeping_categorielijst( $alle, $term->term_id, $diepte + 1, $gekozen, $naam );
	}
}
