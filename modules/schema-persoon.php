<?php
/**
 * Mediamora Toolkit, module: Persoon in schema
 * Vervangt de losse code in functions.php van het child theme (artsenkompas.nl
 * en prikkel-teksten.nl).
 * Wordt alleen geladen als de module aanstaat.
 *
 * Bij praktijken die op één persoon draaien zet deze module een Person-node in
 * het schema van Rank Math, met @id home_url( '/#persoon' ) en worksFor naar de
 * Organization (#organization). Op de Organization komt founder of employee
 * naar #persoon, en desgewenst een omschrijving van de praktijk.
 *
 * Dat gebeurt in het filter rank_math/json_ld, dus overal waar Rank Math de
 * algemene graph zet, ook op de pagina's die Schema op alle pagina's erbij
 * haalt. Staat er geen Organization in de graph, of is de site in Rank Math
 * een persoon (knowledgegraph_type person), dan doet de module niets.
 *
 * Staat er al een node met @id #persoon in de graph, of heeft de Organization
 * al een founder of employee, dan doet de module ook niets: dan staat er nog
 * losse code op de site, en twee Person-nodes is erger dan geen. Het filter
 * draait daarom laat (9999), na Rank Math en na de meeste losse code. Wat de
 * module bij de laatste weergave zag, staat in de optie
 * mm_schema_persoon_bezet, zodat het instellingenscherm het kan melden.
 *
 * De toolkit laadt voor Rank Math, dus RANK_MATH_VERSION bestaat hier nog
 * niet. Daarom pas op plugins_loaded kijken of Rank Math er is.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_SCHEMA_PERSOON_OPTIE = 'mm_schema_persoon';
const MM_SCHEMA_PERSOON_BEZET = 'mm_schema_persoon_bezet';

add_action( 'plugins_loaded', 'mm_schema_persoon_registreren' );

function mm_schema_persoon_registreren() {
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		add_filter( 'rank_math/json_ld', 'mm_schema_persoon_json_ld', 9999 );
	}
}

/**
 * De opgeslagen gegevens, aangevuld met lege waarden.
 */
function mm_schema_persoon_instellingen() {
	$opgeslagen = get_option( MM_SCHEMA_PERSOON_OPTIE, array() );
	return array_merge(
		array(
			'naam'           => '',
			'functie'        => '',
			'omschrijving'   => '',
			'url'            => '',
			'opleiding_naam' => '',
			'opleiding_url'  => '',
			'lid_naam'       => '',
			'lid_url'        => '',
			'sameas'         => array(),
			'foto'           => 0,
			'rol'            => 'founder',
			'praktijk'       => '',
		),
		is_array( $opgeslagen ) ? $opgeslagen : array()
	);
}

/**
 * Is de site in Rank Math een persoon in plaats van een organisatie?
 */
function mm_schema_persoon_site_is_persoon() {
	$titels = get_option( 'rank-math-options-titles', array() );
	return is_array( $titels ) && isset( $titels['knowledgegraph_type'] ) && 'person' === $titels['knowledgegraph_type'];
}

/**
 * De Person-node uit de instellingen, of null zonder naam.
 *
 * @param array  $instellingen Zie mm_schema_persoon_instellingen().
 * @param string $id           Het @id van de persoon.
 * @param string $organisatie  Het @id van de Organization.
 * @param string $foto         URL van de foto, of leeg.
 */
function mm_schema_persoon_node( $instellingen, $id, $organisatie, $foto ) {

	if ( '' === $instellingen['naam'] ) {
		return null;
	}

	$node = array(
		'@type' => 'Person',
		'@id'   => $id,
		'name'  => $instellingen['naam'],
	);

	if ( '' !== $instellingen['functie'] ) {
		$node['jobTitle'] = $instellingen['functie'];
	}
	if ( '' !== $instellingen['omschrijving'] ) {
		$node['description'] = $instellingen['omschrijving'];
	}
	if ( '' !== $instellingen['url'] ) {
		$node['url'] = $instellingen['url'];
	}
	if ( '' !== $foto ) {
		$node['image'] = $foto;
	}
	if ( '' !== $instellingen['opleiding_naam'] ) {
		$node['alumniOf'] = array(
			'@type' => 'EducationalOrganization',
			'name'  => $instellingen['opleiding_naam'],
		);
		if ( '' !== $instellingen['opleiding_url'] ) {
			$node['alumniOf']['url'] = $instellingen['opleiding_url'];
		}
	}
	if ( '' !== $instellingen['lid_naam'] ) {
		$node['memberOf'] = array(
			'@type' => 'Organization',
			'name'  => $instellingen['lid_naam'],
		);
		if ( '' !== $instellingen['lid_url'] ) {
			$node['memberOf']['url'] = $instellingen['lid_url'];
		}
	}
	if ( $instellingen['sameas'] ) {
		$node['sameAs'] = array_values( $instellingen['sameas'] );
	}

	$node['worksFor'] = array( '@id' => $organisatie );

	return $node;
}

/**
 * Zoekt de Organization in de data van Rank Math: eerst op @id, dan de
 * publisher zolang die geen Person is. Geeft de sleutel, of null.
 *
 * @param array  $data
 * @param string $organisatie Het @id van de Organization.
 */
function mm_schema_persoon_zoek_organisatie( $data, $organisatie ) {

	foreach ( $data as $sleutel => $node ) {
		if ( is_array( $node ) && isset( $node['@id'] ) && $organisatie === $node['@id'] ) {
			return $sleutel;
		}
	}

	if ( isset( $data['publisher'] ) && is_array( $data['publisher'] ) && ! empty( $data['publisher']['@type'] ) ) {
		$soorten = (array) $data['publisher']['@type'];
		if ( ! in_array( 'Person', $soorten, true ) ) {
			return 'publisher';
		}
	}

	return null;
}

/**
 * Voegt de persoon toe aan de data van Rank Math. Los van WordPress, zodat het
 * te testen is. Geeft de data terug en in $bezet of er al een persoon stond.
 *
 * @param array     $data         Wat Rank Math in rank_math/json_ld meegeeft.
 * @param array     $instellingen Zie mm_schema_persoon_instellingen().
 * @param string    $id           Het @id van de persoon.
 * @param string    $organisatie  Het @id van de Organization.
 * @param string    $foto         URL van de foto, of leeg.
 * @param bool|null $bezet        Wordt true als er al een persoon in de graph stond.
 */
function mm_schema_persoon_toevoegen( $data, $instellingen, $id, $organisatie, $foto, &$bezet = null ) {

	$bezet = false;

	if ( ! is_array( $data ) ) {
		return $data;
	}

	$sleutel = mm_schema_persoon_zoek_organisatie( $data, $organisatie );
	if ( null === $sleutel ) {
		return $data;
	}

	// Al een persoon: losse code, of een tweede filter. Dan niets.
	foreach ( $data as $node ) {
		if ( is_array( $node ) && isset( $node['@id'] ) && $id === $node['@id'] ) {
			$bezet = true;
			return $data;
		}
	}
	if ( ! empty( $data[ $sleutel ]['founder'] ) || ! empty( $data[ $sleutel ]['employee'] ) ) {
		$bezet = true;
		return $data;
	}

	$persoon = mm_schema_persoon_node( $instellingen, $id, $data[ $sleutel ]['@id'] ?? $organisatie, $foto );
	if ( null === $persoon ) {
		return $data;
	}

	$rol                      = 'employee' === $instellingen['rol'] ? 'employee' : 'founder';
	$data[ $sleutel ][ $rol ] = array( '@id' => $id );
	if ( '' !== $instellingen['praktijk'] ) {
		$data[ $sleutel ]['description'] = $instellingen['praktijk'];
	}

	$data['mm_persoon'] = $persoon;

	return $data;
}

/**
 * @param array $data Wat Rank Math tot nu toe in de graph heeft.
 */
function mm_schema_persoon_json_ld( $data ) {

	if ( mm_schema_persoon_site_is_persoon() ) {
		return $data;
	}

	$instellingen = mm_schema_persoon_instellingen();
	$foto         = $instellingen['foto'] ? (string) wp_get_attachment_image_url( $instellingen['foto'], 'full' ) : '';

	$data = mm_schema_persoon_toevoegen( $data, $instellingen, home_url( '/#persoon' ), home_url( '/#organization' ), $foto, $bezet );

	// Alleen schrijven als het verandert. Autoload, dus lezen kost niets.
	if ( (bool) get_option( MM_SCHEMA_PERSOON_BEZET, false ) !== $bezet ) {
		update_option( MM_SCHEMA_PERSOON_BEZET, $bezet ? 1 : 0, true );
	}

	return $data;
}


/* -------------------------------------------------------------------------
 * Instellingenscherm
 *
 * mm_toolkit_scherm() roept deze functies aan als de module laadt: de korte
 * regel in de statuskolom en het formulier onder de tabel, op het tabblad
 * Vindbaarheid. Het formulier staat buiten dat van de schakelaars.
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_mm_schema_persoon_opslaan', 'mm_schema_persoon_opslaan' );

function mm_schema_persoon_opslaan() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'mm_schema_persoon_opslaan' );

	$veld = function ( $naam ) {
		return isset( $_POST[ $naam ] ) ? wp_unslash( $_POST[ $naam ] ) : '';
	};
	$url  = function ( $waarde ) {
		return (string) esc_url_raw( trim( (string) $waarde ), array( 'http', 'https' ) );
	};

	$sameas = array();
	foreach ( preg_split( '/\R/', (string) $veld( 'mm_persoon_sameas' ) ) as $regel ) {
		$regel = $url( $regel );
		if ( '' !== $regel && ! in_array( $regel, $sameas, true ) ) {
			$sameas[] = $regel;
		}
	}

	$foto = absint( $veld( 'mm_persoon_foto' ) );
	if ( $foto && ! wp_attachment_is_image( $foto ) ) {
		$foto = 0;
	}

	$instellingen = array(
		'naam'           => sanitize_text_field( $veld( 'mm_persoon_naam' ) ),
		'functie'        => sanitize_text_field( $veld( 'mm_persoon_functie' ) ),
		'omschrijving'   => sanitize_textarea_field( $veld( 'mm_persoon_omschrijving' ) ),
		'url'            => $url( $veld( 'mm_persoon_url' ) ),
		'opleiding_naam' => sanitize_text_field( $veld( 'mm_persoon_opleiding_naam' ) ),
		'opleiding_url'  => $url( $veld( 'mm_persoon_opleiding_url' ) ),
		'lid_naam'       => sanitize_text_field( $veld( 'mm_persoon_lid_naam' ) ),
		'lid_url'        => $url( $veld( 'mm_persoon_lid_url' ) ),
		'sameas'         => $sameas,
		'foto'           => $foto,
		'rol'            => 'employee' === $veld( 'mm_persoon_rol' ) ? 'employee' : 'founder',
		'praktijk'       => sanitize_textarea_field( $veld( 'mm_persoon_praktijk' ) ),
	);

	// Autoload: wordt gelezen op elke pagina met schema.
	update_option( MM_SCHEMA_PERSOON_OPTIE, $instellingen, true );

	wp_safe_redirect( add_query_arg( 'mm_persoon', '1', mm_toolkit_scherm_url( 'vindbaarheid' ) ) . '#mm-schema-persoon' );
	exit;
}

/**
 * Korte regel voor de statuskolom.
 */
function mm_schema_persoon_status_regel() {
	if ( ! defined( 'RANK_MATH_VERSION' ) ) {
		return 'Doet niets: Rank Math is niet actief';
	}
	if ( mm_schema_persoon_site_is_persoon() ) {
		return 'Doet niets: in Rank Math is de site zelf een persoon';
	}
	$instellingen = mm_schema_persoon_instellingen();
	if ( '' === $instellingen['naam'] ) {
		return '<span style="color:#b32d2e;">Nog geen naam ingevuld</span> <a href="#mm-schema-persoon">Gegevens</a>';
	}
	if ( get_option( MM_SCHEMA_PERSOON_BEZET, false ) ) {
		return '<span style="color:#b32d2e;">Doet niets: bij de laatste weergave stond er al een persoon in het schema, waarschijnlijk losse code</span>';
	}
	return esc_html( $instellingen['naam'] ) . ' <a href="#mm-schema-persoon">Gegevens</a>';
}

add_action( 'admin_enqueue_scripts', 'mm_schema_persoon_media' );

function mm_schema_persoon_media( $hook ) {
	if ( 'settings_page_' . MM_TOOLKIT_SLUG === $hook && isset( $_GET['tab'] ) && 'vindbaarheid' === $_GET['tab'] ) {
		wp_enqueue_media();
	}
}

/**
 * Het formulier onder de tabel.
 */
function mm_schema_persoon_formulier() {

	$i    = mm_schema_persoon_instellingen();
	$foto = $i['foto'] ? wp_get_attachment_image_url( $i['foto'], 'thumbnail' ) : '';

	echo '<h2 id="mm-schema-persoon" style="margin-top:32px;">Persoon in schema</h2>';

	if ( ! empty( $_GET['mm_persoon'] ) ) {
		echo '<div class="notice notice-success inline"><p>Gegevens opgeslagen. Leeg de cache om ze op de site te zien.</p></div>';
	}

	echo '<p style="max-width:900px;">Komt als Person met <code>@id</code> <code>' . esc_html( home_url( '/#persoon' ) ) . '</code> in het schema, met worksFor naar de organisatie. Alleen de naam is verplicht; lege velden blijven weg.</p>';

	$tekst = function ( $naam, $label, $waarde, $extra = '' ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $naam ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="regular-text" id="' . esc_attr( $naam ) . '" name="' . esc_attr( $naam ) . '" value="' . esc_attr( $waarde ) . '"' . $extra . '></td></tr>';
	};

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_schema_persoon_opslaan">';
	wp_nonce_field( 'mm_schema_persoon_opslaan' );
	echo '<table class="form-table" role="presentation" style="max-width:900px;"><tbody>';

	$tekst( 'mm_persoon_naam', 'Naam', $i['naam'], ' required' );
	$tekst( 'mm_persoon_functie', 'Functie', $i['functie'], ' placeholder="bijvoorbeeld Psychosociaal therapeut"' );
	echo '<tr><th scope="row"><label for="mm_persoon_omschrijving">Omschrijving van de persoon</label></th><td><textarea class="large-text" rows="3" id="mm_persoon_omschrijving" name="mm_persoon_omschrijving">' . esc_textarea( $i['omschrijving'] ) . '</textarea></td></tr>';
	echo '<tr><th scope="row"><label for="mm_persoon_url">Pagina over de persoon</label></th><td><input type="url" class="regular-text" id="mm_persoon_url" name="mm_persoon_url" value="' . esc_attr( $i['url'] ) . '" placeholder="' . esc_attr( home_url( '/over/' ) ) . '"><p class="description">Bijvoorbeeld de Over-pagina. Optioneel.</p></td></tr>';
	echo '<tr><th scope="row"><label for="mm_persoon_opleiding_naam">Opleiding of instituut</label></th><td><input type="text" class="regular-text" id="mm_persoon_opleiding_naam" name="mm_persoon_opleiding_naam" value="' . esc_attr( $i['opleiding_naam'] ) . '" placeholder="Naam"><br><input type="url" class="regular-text" style="margin-top:6px;" id="mm_persoon_opleiding_url" name="mm_persoon_opleiding_url" value="' . esc_attr( $i['opleiding_url'] ) . '" placeholder="https://"><p class="description">Komt als alumniOf (EducationalOrganization). De URL telt alleen met een naam.</p></td></tr>';
	echo '<tr><th scope="row"><label for="mm_persoon_lid_naam">Lid van</label></th><td><input type="text" class="regular-text" id="mm_persoon_lid_naam" name="mm_persoon_lid_naam" value="' . esc_attr( $i['lid_naam'] ) . '" placeholder="Naam"><br><input type="url" class="regular-text" style="margin-top:6px;" id="mm_persoon_lid_url" name="mm_persoon_lid_url" value="' . esc_attr( $i['lid_url'] ) . '" placeholder="https://"><p class="description">Beroepsvereniging of netwerk. Komt als memberOf (Organization). De URL telt alleen met een naam.</p></td></tr>';
	echo '<tr><th scope="row"><label for="mm_persoon_sameas">Profielen</label></th><td><textarea class="large-text code" rows="4" id="mm_persoon_sameas" name="mm_persoon_sameas">' . esc_textarea( implode( "\n", $i['sameas'] ) ) . '</textarea><p class="description">Eén URL per regel: LinkedIn, beroepsvereniging, Google Bedrijfsprofiel en dergelijke. Komt als sameAs.</p></td></tr>';

	echo '<tr><th scope="row">Foto</th><td>';
	echo '<input type="hidden" id="mm_persoon_foto" name="mm_persoon_foto" value="' . (int) $i['foto'] . '">';
	echo '<img id="mm_persoon_foto_voorbeeld" src="' . esc_url( (string) $foto ) . '" alt="" style="display:' . ( $foto ? 'block' : 'none' ) . ';width:96px;height:96px;object-fit:cover;margin-bottom:6px;">';
	echo '<button type="button" class="button" id="mm_persoon_foto_kies">Kies foto</button> ';
	echo '<button type="button" class="button-link" id="mm_persoon_foto_weg" style="display:' . ( $foto ? 'inline' : 'none' ) . ';">Weghalen</button>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="mm_persoon_rol">Rol bij de praktijk</label></th><td><select id="mm_persoon_rol" name="mm_persoon_rol">';
	echo '<option value="founder"' . selected( $i['rol'], 'founder', false ) . '>Oprichter (founder)</option>';
	echo '<option value="employee"' . selected( $i['rol'], 'employee', false ) . '>Medewerker (employee)</option>';
	echo '</select></td></tr>';

	echo '<tr><th scope="row"><label for="mm_persoon_praktijk">Omschrijving van de praktijk</label></th><td><textarea class="large-text" rows="3" id="mm_persoon_praktijk" name="mm_persoon_praktijk">' . esc_textarea( $i['praktijk'] ) . '</textarea><p class="description">Optioneel. Komt als description op de organisatie en vervangt dan wat Rank Math daar zet.</p></td></tr>';

	echo '</tbody></table>';
	submit_button( 'Gegevens opslaan', 'primary', 'submit', true );
	echo '</form>';
	?>
	<script>
	( function () {
		var kies = document.getElementById( 'mm_persoon_foto_kies' ), weg = document.getElementById( 'mm_persoon_foto_weg' );
		var veld = document.getElementById( 'mm_persoon_foto' ), voorbeeld = document.getElementById( 'mm_persoon_foto_voorbeeld' );
		var venster;
		if ( ! kies || ! window.wp || ! wp.media ) {
			return;
		}
		kies.addEventListener( 'click', function () {
			venster = venster || wp.media( { title: 'Foto kiezen', library: { type: 'image' }, multiple: false, button: { text: 'Gebruiken' } } );
			venster.off( 'select' ).on( 'select', function () {
				var foto = venster.state().get( 'selection' ).first().toJSON();
				veld.value = foto.id;
				voorbeeld.src = foto.sizes && foto.sizes.thumbnail ? foto.sizes.thumbnail.url : foto.url;
				voorbeeld.style.display = 'block';
				weg.style.display = 'inline';
			} );
			venster.open();
		} );
		weg.addEventListener( 'click', function () {
			veld.value = '0';
			voorbeeld.style.display = 'none';
			weg.style.display = 'none';
		} );
	} )();
	</script>
	<?php
}
