<?php
/**
 * Mediamora Toolkit, module: Persoon in schema
 * Vervangt de losse code in functions.php van het child theme (artsenkompas.nl
 * en prikkel-teksten.nl).
 * Wordt alleen geladen als de module aanstaat.
 *
 * Zet een of meer personen als Person-node in het schema van Rank Math, elk
 * met worksFor naar de Organization (#organization). De eerste persoon krijgt
 * @id home_url( '/#persoon' ), de volgende home_url( '/#persoon-<slug>' ) op
 * basis van de naam. Op de Organization komt founder of employee naar de
 * personen (één verwijzing bij één persoon, een lijst bij meer), en desgewenst
 * een omschrijving van de praktijk.
 *
 * Is een persoon gekoppeld aan een WordPress-gebruiker, dan haalt de module
 * de Person die Rank Math voor die gebruiker als auteur zet weg (Rank Math
 * zet die onder de sleutel ProfilePage, met als @id de auteurs-URL, of de
 * pagina-URL met #author als de auteursarchieven uit staan). Alle verwijzingen
 * naar dat @id, zoals de author van een BlogPosting, wijzen daarna naar de
 * persoon van de module. Zo staat iedereen maar één keer in de graph.
 *
 * Dat gebeurt in het filter rank_math/json_ld, dus overal waar Rank Math de
 * algemene graph zet, ook op de pagina's die Schema op alle pagina's erbij
 * haalt. Staat er geen Organization in de graph, of is de site in Rank Math
 * een persoon (knowledgegraph_type person), dan doet de module niets.
 *
 * Staat er al een node met een van onze @id's in de graph, of heeft de
 * Organization al een founder of employee, dan doet de module ook niets: dan
 * staat er nog losse code op de site, en twee Person-nodes is erger dan geen.
 * Het filter draait daarom laat (9999), na Rank Math en na de meeste losse
 * code. Wat de module bij de laatste weergave zag, staat in de optie
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
 * Eén persoon met lege waarden.
 *
 * opleidingen en lidmaatschappen: lijsten van array( 'naam', 'url' ).
 * registraties: lijst van array( 'naam', 'erkend', 'url' ).
 * vakgebieden, talen en sameas: lijsten van tekst.
 */
function mm_schema_persoon_leeg() {
	return array(
		'naam'            => '',
		'titel_voor'      => '',
		'titel_na'        => '',
		'functie'         => '',
		'omschrijving'    => '',
		'url'             => '',
		'opleidingen'     => array(),
		'lidmaatschappen' => array(),
		'registraties'    => array(),
		'vakgebieden'     => array(),
		'talen'           => array(),
		'sameas'          => array(),
		'foto'            => 0,
		'rol'             => 'founder',
		'gebruiker'       => 0,
	);
}

/**
 * Zet de opgeslagen optie om naar array( 'personen' => ..., 'praktijk' => ... ).
 * Kan ook het formaat van 1.20 t/m 1.23 aan: één persoon met platte velden,
 * en opleiding_naam/url en lid_naam/url als losse velden. Die worden de
 * eerste regel van opleidingen en lidmaatschappen. Opgeslagen in het nieuwe
 * formaat wordt pas bij Gegevens opslaan; tot die tijd leest de module het
 * oude formaat zo om.
 *
 * @param mixed $opgeslagen Wat in mm_schema_persoon staat.
 */
function mm_schema_persoon_omzetten( $opgeslagen ) {

	$opgeslagen = is_array( $opgeslagen ) ? $opgeslagen : array();
	$praktijk   = isset( $opgeslagen['praktijk'] ) ? (string) $opgeslagen['praktijk'] : '';
	$personen   = array();

	if ( isset( $opgeslagen['personen'] ) && is_array( $opgeslagen['personen'] ) ) {
		foreach ( $opgeslagen['personen'] as $persoon ) {
			if ( is_array( $persoon ) ) {
				$personen[] = array_merge( mm_schema_persoon_leeg(), $persoon );
			}
		}
		return array( 'personen' => $personen, 'praktijk' => $praktijk );
	}

	if ( isset( $opgeslagen['naam'] ) ) {
		$persoon = array_merge( mm_schema_persoon_leeg(), array_intersect_key( $opgeslagen, mm_schema_persoon_leeg() ) );
		foreach ( array( 'opleiding' => 'opleidingen', 'lid' => 'lidmaatschappen' ) as $oud => $nieuw ) {
			$naam = isset( $opgeslagen[ $oud . '_naam' ] ) ? (string) $opgeslagen[ $oud . '_naam' ] : '';
			$url  = isset( $opgeslagen[ $oud . '_url' ] ) ? (string) $opgeslagen[ $oud . '_url' ] : '';
			if ( '' !== $naam || '' !== $url ) {
				$persoon[ $nieuw ] = array( array( 'naam' => $naam, 'url' => $url ) );
			}
		}
		$personen[] = $persoon;
	}

	return array( 'personen' => $personen, 'praktijk' => $praktijk );
}

/**
 * De opgeslagen gegevens in het nieuwe formaat.
 */
function mm_schema_persoon_instellingen() {
	return mm_schema_persoon_omzetten( get_option( MM_SCHEMA_PERSOON_OPTIE, array() ) );
}

/**
 * Is de site in Rank Math een persoon in plaats van een organisatie?
 */
function mm_schema_persoon_site_is_persoon() {
	$titels = get_option( 'rank-math-options-titles', array() );
	return is_array( $titels ) && isset( $titels['knowledgegraph_type'] ) && 'person' === $titels['knowledgegraph_type'];
}

/**
 * De @id's van de personen: de eerste $basis, de volgende $basis-<slug van
 * de naam>, met -2, -3 erachter als die al bestaat.
 *
 * @param array  $personen Personen met een naam.
 * @param string $basis    home_url( '/#persoon' ).
 */
function mm_schema_persoon_ids( $personen, $basis ) {

	$ids = array();

	foreach ( $personen as $nr => $persoon ) {
		if ( ! $ids ) {
			$ids[ $nr ] = $basis;
			continue;
		}
		$slug = sanitize_title( $persoon['naam'] );
		$slug = '' !== $slug ? $slug : (string) ( count( $ids ) + 1 );
		$id   = $basis . '-' . $slug;
		for ( $n = 2; in_array( $id, $ids, true ); $n++ ) {
			$id = $basis . '-' . $slug . '-' . $n;
		}
		$ids[ $nr ] = $id;
	}

	return $ids;
}

/**
 * De personen met een naam, op volgorde, elk met hun @id onder 'id'.
 *
 * @param array  $personen Zie mm_schema_persoon_omzetten().
 * @param string $basis    home_url( '/#persoon' ).
 */
function mm_schema_persoon_met_ids( $personen, $basis ) {

	$personen = array_values(
		array_filter(
			$personen,
			function ( $persoon ) {
				return '' !== $persoon['naam'];
			}
		)
	);

	foreach ( mm_schema_persoon_ids( $personen, $basis ) as $nr => $id ) {
		$personen[ $nr ]['id'] = $id;
	}

	return $personen;
}

/**
 * Eén waarde als er één is, anders de lijst.
 */
function mm_schema_persoon_een_of_lijst( $lijst ) {
	return 1 === count( $lijst ) ? $lijst[0] : $lijst;
}

/**
 * Organisaties uit regels met naam en url. Regels zonder naam vallen weg.
 *
 * @param array  $regels Lijst van array( 'naam', 'url' ).
 * @param string $soort  @type van de organisatie.
 */
function mm_schema_persoon_organisaties( $regels, $soort ) {

	$uit = array();

	foreach ( (array) $regels as $regel ) {
		if ( ! is_array( $regel ) || '' === (string) ( $regel['naam'] ?? '' ) ) {
			continue;
		}
		$organisatie = array(
			'@type' => $soort,
			'name'  => $regel['naam'],
		);
		if ( '' !== (string) ( $regel['url'] ?? '' ) ) {
			$organisatie['url'] = $regel['url'];
		}
		$uit[] = $organisatie;
	}

	return $uit;
}

/**
 * De Person-node van één persoon, of null zonder naam.
 *
 * @param array  $persoon     Zie mm_schema_persoon_leeg().
 * @param string $id          Het @id van de persoon.
 * @param string $organisatie Het @id van de Organization.
 * @param string $foto        URL van de foto, of leeg.
 */
function mm_schema_persoon_node( $persoon, $id, $organisatie, $foto ) {

	$persoon = array_merge( mm_schema_persoon_leeg(), $persoon );

	if ( '' === $persoon['naam'] ) {
		return null;
	}

	$node = array(
		'@type' => 'Person',
		'@id'   => $id,
		'name'  => $persoon['naam'],
	);

	if ( '' !== $persoon['titel_voor'] ) {
		$node['honorificPrefix'] = $persoon['titel_voor'];
	}
	if ( '' !== $persoon['titel_na'] ) {
		$node['honorificSuffix'] = $persoon['titel_na'];
	}
	if ( '' !== $persoon['functie'] ) {
		$node['jobTitle'] = $persoon['functie'];
	}
	if ( '' !== $persoon['omschrijving'] ) {
		$node['description'] = $persoon['omschrijving'];
	}
	if ( '' !== $persoon['url'] ) {
		$node['url'] = $persoon['url'];
	}
	if ( '' !== $foto ) {
		$node['image'] = $foto;
	}

	$opleidingen = mm_schema_persoon_organisaties( $persoon['opleidingen'], 'EducationalOrganization' );
	if ( $opleidingen ) {
		$node['alumniOf'] = mm_schema_persoon_een_of_lijst( $opleidingen );
	}
	$lidmaatschappen = mm_schema_persoon_organisaties( $persoon['lidmaatschappen'], 'Organization' );
	if ( $lidmaatschappen ) {
		$node['memberOf'] = mm_schema_persoon_een_of_lijst( $lidmaatschappen );
	}

	$registraties = array();
	foreach ( (array) $persoon['registraties'] as $regel ) {
		if ( ! is_array( $regel ) || '' === (string) ( $regel['naam'] ?? '' ) ) {
			continue;
		}
		$registratie = array(
			'@type' => 'EducationalOccupationalCredential',
			'name'  => $regel['naam'],
		);
		$erkend = mm_schema_persoon_organisaties( array( array( 'naam' => $regel['erkend'] ?? '', 'url' => $regel['url'] ?? '' ) ), 'Organization' );
		if ( $erkend ) {
			$registratie['recognizedBy'] = $erkend[0];
		}
		$registraties[] = $registratie;
	}
	if ( $registraties ) {
		$node['hasCredential'] = mm_schema_persoon_een_of_lijst( $registraties );
	}

	if ( $persoon['vakgebieden'] ) {
		$node['knowsAbout'] = array_values( $persoon['vakgebieden'] );
	}
	if ( $persoon['talen'] ) {
		$node['knowsLanguage'] = array_values( $persoon['talen'] );
	}
	if ( $persoon['sameas'] ) {
		$node['sameAs'] = array_values( $persoon['sameas'] );
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
 * Vervangt in een waarde, en alles daaronder, elke node met een @id uit
 * $auteurs door een verwijzing naar $id. Had de node een name, dan krijgt de
 * verwijzing de naam van de persoon.
 */
function mm_schema_persoon_verwijzingen( $waarde, $auteurs, $id, $naam ) {

	if ( ! is_array( $waarde ) ) {
		return $waarde;
	}

	if ( isset( $waarde['@id'] ) && in_array( $waarde['@id'], $auteurs, true ) ) {
		$verwijzing = array( '@id' => $id );
		if ( isset( $waarde['name'] ) ) {
			$verwijzing['name'] = $naam;
		}
		return $verwijzing;
	}

	foreach ( $waarde as $sleutel => $deel ) {
		$waarde[ $sleutel ] = mm_schema_persoon_verwijzingen( $deel, $auteurs, $id, $naam );
	}

	return $waarde;
}

/**
 * Haalt de auteur-Person van Rank Math weg en laat alle verwijzingen ernaar
 * naar de persoon van de module wijzen. Op een auteursarchief is die
 * Person de mainEntityOfPage van de ProfilePage; die pagina krijgt dan
 * mainEntity naar de persoon, zodat de koppeling blijft.
 *
 * @param array    $data    De graph.
 * @param string[] $auteurs De @id's die Rank Math voor de gebruiker gebruikt.
 * @param string   $id      Het @id van de persoon.
 * @param string   $naam    De naam van de persoon.
 */
function mm_schema_persoon_auteur_samenvoegen( $data, $auteurs, $id, $naam ) {

	$auteurs = array_values( array_filter( (array) $auteurs, 'is_string' ) );
	if ( ! $auteurs ) {
		return $data;
	}

	$paginas = array();
	foreach ( $data as $sleutel => $node ) {
		if ( is_array( $node ) && isset( $node['@id'] ) && in_array( $node['@id'], $auteurs, true ) && in_array( 'Person', (array) ( $node['@type'] ?? array() ), true ) ) {
			if ( isset( $node['mainEntityOfPage']['@id'] ) ) {
				$paginas[] = $node['mainEntityOfPage']['@id'];
			}
			unset( $data[ $sleutel ] );
		}
	}

	foreach ( $data as $sleutel => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		foreach ( $node as $eigenschap => $waarde ) {
			$data[ $sleutel ][ $eigenschap ] = mm_schema_persoon_verwijzingen( $waarde, $auteurs, $id, $naam );
		}
		if ( isset( $node['@id'] ) && in_array( $node['@id'], $paginas, true ) && ! isset( $node['mainEntity'] ) ) {
			$data[ $sleutel ]['mainEntity'] = array( '@id' => $id );
		}
	}

	return $data;
}

/**
 * Voegt de personen toe aan de data van Rank Math. Los van WordPress, zodat
 * het te testen is. Geeft de data terug en in $bezet of er al een persoon
 * stond.
 *
 * @param array     $data        Wat Rank Math in rank_math/json_ld meegeeft.
 * @param array     $personen    Personen zoals mm_schema_persoon_leeg(), plus
 *                               'id' (het @id), 'foto' (URL of leeg) en
 *                               'auteurs' (@id's van de auteur-Person van de
 *                               gekoppelde gebruiker).
 * @param string    $praktijk    Omschrijving van de praktijk, of leeg.
 * @param string    $organisatie Het @id van de Organization.
 * @param bool|null $bezet       Wordt true als er al een persoon in de graph stond.
 */
function mm_schema_persoon_toevoegen( $data, $personen, $praktijk, $organisatie, &$bezet = null ) {

	$bezet = false;

	if ( ! is_array( $data ) ) {
		return $data;
	}

	$sleutel = mm_schema_persoon_zoek_organisatie( $data, $organisatie );
	if ( null === $sleutel ) {
		return $data;
	}

	// Al een persoon: losse code, of een tweede filter. Dan niets.
	$ids = array_column( $personen, 'id' );
	foreach ( $data as $node ) {
		if ( is_array( $node ) && isset( $node['@id'] ) && in_array( $node['@id'], $ids, true ) ) {
			$bezet = true;
			return $data;
		}
	}
	if ( ! empty( $data[ $sleutel ]['founder'] ) || ! empty( $data[ $sleutel ]['employee'] ) ) {
		$bezet = true;
		return $data;
	}

	$nodes  = array();
	$rollen = array(
		'founder'  => array(),
		'employee' => array(),
	);
	foreach ( $personen as $nr => $persoon ) {
		$node = mm_schema_persoon_node( $persoon, $persoon['id'], $data[ $sleutel ]['@id'] ?? $organisatie, $persoon['foto'] ?? '' );
		if ( null === $node ) {
			continue;
		}
		$nodes[ $nr ] = $node;
		$rol          = 'employee' === ( $persoon['rol'] ?? '' ) ? 'employee' : 'founder';
		$rollen[ $rol ][] = array( '@id' => $persoon['id'] );
	}
	if ( ! $nodes ) {
		return $data;
	}

	foreach ( $rollen as $rol => $verwijzingen ) {
		if ( $verwijzingen ) {
			$data[ $sleutel ][ $rol ] = mm_schema_persoon_een_of_lijst( $verwijzingen );
		}
	}
	if ( '' !== $praktijk ) {
		$data[ $sleutel ]['description'] = $praktijk;
	}

	$teller = 0;
	foreach ( $nodes as $node ) {
		$data[ 0 === $teller++ ? 'mm_persoon' : 'mm_persoon_' . $teller ] = $node;
	}

	foreach ( $nodes as $nr => $node ) {
		if ( ! empty( $personen[ $nr ]['auteurs'] ) ) {
			$data = mm_schema_persoon_auteur_samenvoegen( $data, $personen[ $nr ]['auteurs'], $node['@id'], $node['name'] );
		}
	}

	return $data;
}

/**
 * De @id's die Rank Math voor de auteur-Person van een gebruiker gebruikt:
 * de auteurs-URL (met de slug van ASE Obfuscate Author Slugs, als dat aan
 * staat), en als de auteursarchieven uit staan de pagina-URL met #author.
 * Dat laatste alleen op een bericht of pagina van die gebruiker.
 *
 * @param int   $gebruiker ID van de WordPress-gebruiker, of 0.
 * @param array $data      De graph.
 */
function mm_schema_persoon_auteur_ids( $gebruiker, $data ) {

	if ( ! $gebruiker ) {
		return array();
	}

	$ids = array( get_author_posts_url( $gebruiker ) );

	$bericht = is_singular() ? get_queried_object() : null;
	if ( $bericht instanceof WP_Post && (int) $bericht->post_author === $gebruiker ) {
		foreach ( $data as $node ) {
			if ( is_array( $node ) && isset( $node['@id'] ) && is_string( $node['@id'] ) && '#author' === substr( $node['@id'], -7 ) && in_array( 'Person', (array) ( $node['@type'] ?? array() ), true ) ) {
				$ids[] = $node['@id'];
			}
		}
	}

	return $ids;
}

/**
 * @param array $data Wat Rank Math tot nu toe in de graph heeft.
 */
function mm_schema_persoon_json_ld( $data ) {

	if ( mm_schema_persoon_site_is_persoon() || ! is_array( $data ) ) {
		return $data;
	}

	$instellingen = mm_schema_persoon_instellingen();
	$personen     = mm_schema_persoon_met_ids( $instellingen['personen'], home_url( '/#persoon' ) );

	foreach ( $personen as $nr => $persoon ) {
		$personen[ $nr ]['foto']    = $persoon['foto'] ? (string) wp_get_attachment_image_url( $persoon['foto'], 'full' ) : '';
		$personen[ $nr ]['auteurs'] = mm_schema_persoon_auteur_ids( (int) $persoon['gebruiker'], $data );
	}

	$data = mm_schema_persoon_toevoegen( $data, $personen, $instellingen['praktijk'], home_url( '/#organization' ), $bezet );

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

/**
 * De niet-lege regels van een tekstvak, zonder spaties eromheen.
 */
function mm_schema_persoon_regels( $tekst ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $tekst ) ), 'strlen' ) );
}

/**
 * Regels in de vorm "deel | deel | deel" als lijst van arrays met $velden als
 * sleutels. Regels zonder inhoud vallen weg.
 *
 * @param string   $tekst
 * @param string[] $velden Sleutels; het veld 'url' wordt als URL behandeld.
 */
function mm_schema_persoon_regels_delen( $tekst, $velden ) {

	$uit = array();

	foreach ( mm_schema_persoon_regels( $tekst ) as $regel ) {
		$delen = array_map( 'trim', explode( '|', $regel, count( $velden ) ) );
		$rij   = array();
		foreach ( $velden as $nr => $veld ) {
			$deel         = $delen[ $nr ] ?? '';
			$rij[ $veld ] = 'url' === $veld ? (string) esc_url_raw( $deel, array( 'http', 'https' ) ) : sanitize_text_field( $deel );
		}
		if ( '' !== implode( '', $rij ) ) {
			$uit[] = $rij;
		}
	}

	return $uit;
}

/**
 * Terug naar regels voor het tekstvak.
 */
function mm_schema_persoon_delen_tekst( $rijen ) {

	$regels = array();

	foreach ( (array) $rijen as $rij ) {
		$delen = array_values( (array) $rij );
		while ( $delen && '' === end( $delen ) ) {
			array_pop( $delen );
		}
		$regels[] = implode( ' | ', $delen );
	}

	return implode( "\n", $regels );
}

function mm_schema_persoon_opslaan() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'mm_schema_persoon_opslaan' );

	$url = function ( $waarde ) {
		return (string) esc_url_raw( trim( (string) $waarde ), array( 'http', 'https' ) );
	};

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce gecontroleerd met check_admin_referer() hierboven; elk veld wordt hieronder per soort opgeschoond.
	$invoer = isset( $_POST['mm_personen'] ) && is_array( $_POST['mm_personen'] ) ? wp_unslash( $_POST['mm_personen'] ) : array();

	$personen = array();
	foreach ( $invoer as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$veld = function ( $naam ) use ( $p ) {
			return isset( $p[ $naam ] ) && is_string( $p[ $naam ] ) ? $p[ $naam ] : '';
		};

		$naam = sanitize_text_field( $veld( 'naam' ) );
		if ( '' === $naam ) {
			continue;
		}

		$sameas = array();
		foreach ( mm_schema_persoon_regels( $veld( 'sameas' ) ) as $regel ) {
			$regel = $url( $regel );
			if ( '' !== $regel && ! in_array( $regel, $sameas, true ) ) {
				$sameas[] = $regel;
			}
		}

		$vakgebieden = array();
		foreach ( mm_schema_persoon_regels( $veld( 'vakgebieden' ) ) as $regel ) {
			$regel = sanitize_text_field( $regel );
			if ( '' !== $regel && ! in_array( $regel, $vakgebieden, true ) ) {
				$vakgebieden[] = $regel;
			}
		}

		// BCP 47 in grote lijnen: nl, en, nl-BE, zh-Hant.
		$talen = array();
		foreach ( mm_schema_persoon_regels( $veld( 'talen' ) ) as $regel ) {
			if ( preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $regel ) && ! in_array( $regel, $talen, true ) ) {
				$talen[] = $regel;
			}
		}

		$foto = absint( $veld( 'foto' ) );
		if ( $foto && ! wp_attachment_is_image( $foto ) ) {
			$foto = 0;
		}

		$gebruiker = absint( $veld( 'gebruiker' ) );
		if ( $gebruiker && ! get_userdata( $gebruiker ) ) {
			$gebruiker = 0;
		}

		$personen[] = array(
			'naam'            => $naam,
			'titel_voor'      => sanitize_text_field( $veld( 'titel_voor' ) ),
			'titel_na'        => sanitize_text_field( $veld( 'titel_na' ) ),
			'functie'         => sanitize_text_field( $veld( 'functie' ) ),
			'omschrijving'    => sanitize_textarea_field( $veld( 'omschrijving' ) ),
			'url'             => $url( $veld( 'url' ) ),
			'opleidingen'     => mm_schema_persoon_regels_delen( $veld( 'opleidingen' ), array( 'naam', 'url' ) ),
			'lidmaatschappen' => mm_schema_persoon_regels_delen( $veld( 'lidmaatschappen' ), array( 'naam', 'url' ) ),
			'registraties'    => mm_schema_persoon_regels_delen( $veld( 'registraties' ), array( 'naam', 'erkend', 'url' ) ),
			'vakgebieden'     => $vakgebieden,
			'talen'           => $talen,
			'sameas'          => $sameas,
			'foto'            => $foto,
			'rol'             => 'employee' === $veld( 'rol' ) ? 'employee' : 'founder',
			'gebruiker'       => $gebruiker,
		);
	}

	$instellingen = array(
		'personen' => $personen,
		'praktijk' => isset( $_POST['mm_persoon_praktijk'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mm_persoon_praktijk'] ) ) : '',
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
	$namen = array_values( array_filter( array_column( mm_schema_persoon_instellingen()['personen'], 'naam' ), 'strlen' ) );
	if ( ! $namen ) {
		return '<span style="color:#b32d2e;">Nog geen persoon ingevuld</span> <a href="#mm-schema-persoon">Gegevens</a>';
	}
	if ( get_option( MM_SCHEMA_PERSOON_BEZET, false ) ) {
		return '<span style="color:#b32d2e;">Doet niets: bij de laatste weergave stond er al een persoon in het schema, waarschijnlijk losse code</span>';
	}
	$wie = 1 === count( $namen ) ? esc_html( $namen[0] ) : count( $namen ) . ' personen';
	return $wie . ' <a href="#mm-schema-persoon">Gegevens</a>';
}

add_action( 'admin_enqueue_scripts', 'mm_schema_persoon_media' );

function mm_schema_persoon_media( $hook ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Bepaalt alleen of de mediabibliotheek geladen wordt.
	if ( 'settings_page_' . MM_TOOLKIT_SLUG === $hook && isset( $_GET['tab'] ) && 'vindbaarheid' === $_GET['tab'] ) {
		wp_enqueue_media();
	}
}

/**
 * Het blok met de velden van één persoon.
 *
 * @param string $nr         Volgnummer in de namen van de velden, of __i__ voor het sjabloon.
 * @param array  $p          Zie mm_schema_persoon_leeg().
 * @param array  $gebruikers ID => weergavenaam.
 */
function mm_schema_persoon_blok( $nr, $p, $gebruikers ) {

	$naam = function ( $veld ) use ( $nr ) {
		return 'mm_personen[' . $nr . '][' . $veld . ']';
	};
	$id   = function ( $veld ) use ( $nr ) {
		return 'mm_persoon_' . $nr . '_' . $veld;
	};
	$rij  = function ( $veld, $label, $invoer, $uitleg = '' ) use ( $id ) {
		$uitleg = '' !== $uitleg ? '<p class="description">' . $uitleg . '</p>' : '';
		return '<tr><th scope="row"><label for="' . esc_attr( $id( $veld ) ) . '">' . esc_html( $label ) . '</label></th><td>' . $invoer . $uitleg . '</td></tr>';
	};
	$tekst = function ( $veld, $waarde, $extra = '', $soort = 'text' ) use ( $naam, $id ) {
		return '<input type="' . $soort . '" class="regular-text" id="' . esc_attr( $id( $veld ) ) . '" name="' . esc_attr( $naam( $veld ) ) . '" value="' . esc_attr( $waarde ) . '"' . $extra . '>';
	};
	$vak  = function ( $veld, $waarde, $regels = 3, $code = false ) use ( $naam, $id ) {
		return '<textarea class="large-text' . ( $code ? ' code' : '' ) . '" rows="' . (int) $regels . '" id="' . esc_attr( $id( $veld ) ) . '" name="' . esc_attr( $naam( $veld ) ) . '">' . esc_textarea( $waarde ) . '</textarea>';
	};

	$foto = $p['foto'] ? (string) wp_get_attachment_image_url( $p['foto'], 'thumbnail' ) : '';

	$h  = '<div class="mm-persoon" style="max-width:900px;border:1px solid #c3c4c7;background:#fff;padding:4px 16px 8px;margin:16px 0;">';
	$h .= '<p style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;"><strong class="mm-persoon-kop"></strong><span class="mm-persoon-id" style="color:#646970;"></span><span style="margin-left:auto;">';
	$h .= '<button type="button" class="button button-small" data-actie="omhoog">Omhoog</button> ';
	$h .= '<button type="button" class="button button-small" data-actie="omlaag">Omlaag</button> ';
	$h .= '<button type="button" class="button-link button-link-delete" data-actie="weg">Verwijderen</button></span></p>';
	$h .= '<table class="form-table" role="presentation"><tbody>';

	$h .= $rij( 'naam', 'Naam', $tekst( 'naam', $p['naam'], ' required' ) );
	$h .= $rij( 'titel_voor', 'Titel vóór de naam', $tekst( 'titel_voor', $p['titel_voor'], ' placeholder="bijvoorbeeld dr."' ), 'Komt als honorificPrefix.' );
	$h .= $rij( 'titel_na', 'Titel na de naam', $tekst( 'titel_na', $p['titel_na'], ' placeholder="bijvoorbeeld RA, MSc, PhD"' ), 'Komt als honorificSuffix.' );
	$h .= $rij( 'functie', 'Functie', $tekst( 'functie', $p['functie'], ' placeholder="bijvoorbeeld Psychosociaal therapeut"' ) );
	$h .= $rij( 'omschrijving', 'Omschrijving van de persoon', $vak( 'omschrijving', $p['omschrijving'] ) );
	$h .= $rij( 'url', 'Pagina over de persoon', $tekst( 'url', $p['url'], ' placeholder="' . esc_attr( home_url( '/over/' ) ) . '"', 'url' ), 'Bijvoorbeeld de Over-pagina. Optioneel.' );
	$h .= $rij( 'vakgebieden', 'Vakgebieden', $vak( 'vakgebieden', implode( "\n", $p['vakgebieden'] ) ), 'Eén onderwerp per regel. Komt als knowsAbout.' );
	$h .= $rij( 'registraties', 'Registraties', $vak( 'registraties', mm_schema_persoon_delen_tekst( $p['registraties'] ), 3, true ), 'Eén per regel: <code>Naam | Erkend door | URL</code>, bijvoorbeeld BIG-registratie, KNGF, een kwaliteitsregister of RA. De URL is optioneel en telt alleen met "Erkend door". Komt als hasCredential.' );
	$h .= $rij( 'opleidingen', 'Opleidingen', $vak( 'opleidingen', mm_schema_persoon_delen_tekst( $p['opleidingen'] ), 3, true ), 'Eén per regel: <code>Naam | URL</code>, de URL is optioneel. Komt als alumniOf (EducationalOrganization).' );
	$h .= $rij( 'lidmaatschappen', 'Lid van', $vak( 'lidmaatschappen', mm_schema_persoon_delen_tekst( $p['lidmaatschappen'] ), 3, true ), 'Beroepsvereniging of netwerk, één per regel: <code>Naam | URL</code>, de URL is optioneel. Komt als memberOf (Organization).' );
	$h .= $rij( 'talen', 'Talen', $vak( 'talen', implode( "\n", $p['talen'] ), 2, true ), 'Optioneel. Eén taalcode per regel, zoals <code>nl</code> of <code>en</code>. Komt als knowsLanguage.' );
	$h .= $rij( 'sameas', 'Profielen', $vak( 'sameas', implode( "\n", $p['sameas'] ), 4, true ), 'Eén URL per regel: LinkedIn, beroepsvereniging, Google Bedrijfsprofiel en dergelijke. Komt als sameAs.' );

	$h .= '<tr><th scope="row">Foto</th><td>';
	$h .= '<input type="hidden" class="mm-persoon-foto" name="' . esc_attr( $naam( 'foto' ) ) . '" value="' . (int) $p['foto'] . '">';
	$h .= '<img class="mm-persoon-foto-voorbeeld" src="' . esc_url( $foto ) . '" alt="" style="display:' . ( $foto ? 'block' : 'none' ) . ';width:96px;height:96px;object-fit:cover;margin-bottom:6px;">';
	$h .= '<button type="button" class="button" data-actie="foto">Kies foto</button> ';
	$h .= '<button type="button" class="button-link" data-actie="foto-weg" style="display:' . ( $foto ? 'inline' : 'none' ) . ';">Weghalen</button>';
	$h .= '</td></tr>';

	$rol  = '<select id="' . esc_attr( $id( 'rol' ) ) . '" name="' . esc_attr( $naam( 'rol' ) ) . '">';
	$rol .= '<option value="founder"' . selected( $p['rol'], 'founder', false ) . '>Oprichter (founder)</option>';
	$rol .= '<option value="employee"' . selected( $p['rol'], 'employee', false ) . '>Medewerker (employee)</option>';
	$rol .= '</select>';
	$h   .= $rij( 'rol', 'Rol bij de praktijk', $rol );

	$keuze = '<select id="' . esc_attr( $id( 'gebruiker' ) ) . '" name="' . esc_attr( $naam( 'gebruiker' ) ) . '"><option value="0">Geen</option>';
	foreach ( $gebruikers as $gebruiker => $weergave ) {
		$keuze .= '<option value="' . (int) $gebruiker . '"' . selected( (int) $p['gebruiker'], (int) $gebruiker, false ) . '>' . esc_html( $weergave ) . '</option>';
	}
	$keuze .= '</select>';
	$h     .= $rij( 'gebruiker', 'WordPress-gebruiker', $keuze, 'Optioneel. Op berichten en pagina\'s van deze schrijver wijst de auteur dan naar deze persoon, en verdwijnt de losse auteur-Person van Rank Math.' );

	$h .= '</tbody></table></div>';

	return $h;
}

/**
 * Het formulier onder de tabel.
 */
function mm_schema_persoon_formulier() {

	$i = mm_schema_persoon_instellingen();

	// Wie berichten kan schrijven, plus wie al gekoppeld is.
	$gebruikers = array();
	foreach ( get_users( array( 'capability' => 'edit_posts', 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) ) as $gebruiker ) {
		$gebruikers[ (int) $gebruiker->ID ] = $gebruiker->display_name;
	}
	foreach ( $i['personen'] as $persoon ) {
		$gekoppeld = $persoon['gebruiker'] ? get_userdata( (int) $persoon['gebruiker'] ) : false;
		if ( $gekoppeld && ! isset( $gebruikers[ (int) $gekoppeld->ID ] ) ) {
			$gebruikers[ (int) $gekoppeld->ID ] = $gekoppeld->display_name;
		}
	}

	echo '<h2 id="mm-schema-persoon" style="margin-top:32px;">Persoon in schema</h2>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen gelezen om te bepalen wat er getoond wordt, er wordt niets opgeslagen.
	if ( ! empty( $_GET['mm_persoon'] ) ) {
		echo '<div class="notice notice-success inline"><p>Gegevens opgeslagen. Leeg de cache om ze op de site te zien.</p></div>';
	}

	echo '<p style="max-width:900px;">Elke persoon komt als Person in het schema, met worksFor naar de organisatie. De eerste krijgt <code>@id</code> <code>' . esc_html( home_url( '/#persoon' ) ) . '</code>, de volgende <code>' . esc_html( home_url( '/#persoon-' ) ) . '</code> met de naam erachter. Alleen de naam is verplicht; lege velden blijven weg.</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_schema_persoon_opslaan">';
	wp_nonce_field( 'mm_schema_persoon_opslaan' );

	echo '<div id="mm-personen" data-volgende="' . count( $i['personen'] ) . '">';
	foreach ( array_values( $i['personen'] ) as $nr => $persoon ) {
		echo mm_schema_persoon_blok( (string) $nr, $persoon, $gebruikers ); // phpcs:ignore WordPress.Security.EscapeOutput -- in het blok ge-escaped.
	}
	echo '</div>';
	echo '<template id="mm-persoon-sjabloon">' . mm_schema_persoon_blok( '__i__', mm_schema_persoon_leeg(), $gebruikers ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p><button type="button" class="button" id="mm-persoon-erbij">Persoon toevoegen</button></p>';

	echo '<table class="form-table" role="presentation" style="max-width:900px;"><tbody>';
	echo '<tr><th scope="row"><label for="mm_persoon_praktijk">Omschrijving van de praktijk</label></th><td><textarea class="large-text" rows="3" id="mm_persoon_praktijk" name="mm_persoon_praktijk">' . esc_textarea( $i['praktijk'] ) . '</textarea><p class="description">Optioneel. Komt als description op de organisatie en vervangt dan wat Rank Math daar zet.</p></td></tr>';
	echo '</tbody></table>';

	submit_button( 'Gegevens opslaan', 'primary', 'submit', true );
	echo '</form>';
	?>
	<script>
	( function () {
		var lijst = document.getElementById( 'mm-personen' ), sjabloon = document.getElementById( 'mm-persoon-sjabloon' );
		var erbij = document.getElementById( 'mm-persoon-erbij' );
		var basis = <?php echo wp_json_encode( home_url( '/#persoon' ) ); ?>;
		if ( ! lijst || ! sjabloon || ! erbij ) {
			return;
		}

		function bijwerken() {
			var blokken = lijst.querySelectorAll( '.mm-persoon' );
			blokken.forEach( function ( blok, nr ) {
				blok.querySelector( '.mm-persoon-kop' ).textContent = 'Persoon ' + ( nr + 1 );
				blok.querySelector( '.mm-persoon-id' ).textContent = 0 === nr ? basis : basis + '-<naam>';
				blok.querySelector( '[data-actie="omhoog"]' ).disabled = 0 === nr;
				blok.querySelector( '[data-actie="omlaag"]' ).disabled = nr === blokken.length - 1;
			} );
		}

		erbij.addEventListener( 'click', function () {
			var nr = parseInt( lijst.getAttribute( 'data-volgende' ), 10 ) || 0;
			lijst.setAttribute( 'data-volgende', nr + 1 );
			lijst.insertAdjacentHTML( 'beforeend', sjabloon.innerHTML.replace( /__i__/g, String( nr ) ) );
			bijwerken();
			lijst.lastElementChild.querySelector( 'input[type="text"]' ).focus();
		} );

		lijst.addEventListener( 'click', function ( e ) {
			var knop = e.target.closest( '[data-actie]' ), blok, veld, voorbeeld, weg, venster;
			if ( ! knop ) {
				return;
			}
			blok = knop.closest( '.mm-persoon' );
			switch ( knop.getAttribute( 'data-actie' ) ) {
				case 'omhoog':
					if ( blok.previousElementSibling ) {
						lijst.insertBefore( blok, blok.previousElementSibling );
					}
					break;
				case 'omlaag':
					if ( blok.nextElementSibling ) {
						lijst.insertBefore( blok.nextElementSibling, blok );
					}
					break;
				case 'weg':
					if ( window.confirm( 'Deze persoon verwijderen? Dat gaat pas echt bij Gegevens opslaan.' ) ) {
						blok.remove();
					}
					break;
				case 'foto':
				case 'foto-weg':
					veld = blok.querySelector( '.mm-persoon-foto' );
					voorbeeld = blok.querySelector( '.mm-persoon-foto-voorbeeld' );
					weg = blok.querySelector( '[data-actie="foto-weg"]' );
					if ( 'foto-weg' === knop.getAttribute( 'data-actie' ) ) {
						veld.value = '0';
						voorbeeld.style.display = 'none';
						weg.style.display = 'none';
						break;
					}
					if ( ! window.wp || ! wp.media ) {
						break;
					}
					venster = wp.media( { title: 'Foto kiezen', library: { type: 'image' }, multiple: false, button: { text: 'Gebruiken' } } );
					venster.on( 'select', function () {
						var foto = venster.state().get( 'selection' ).first().toJSON();
						veld.value = foto.id;
						voorbeeld.src = foto.sizes && foto.sizes.thumbnail ? foto.sizes.thumbnail.url : foto.url;
						voorbeeld.style.display = 'block';
						weg.style.display = 'inline';
					} );
					venster.open();
					break;
			}
			bijwerken();
		} );

		bijwerken();
	} )();
	</script>
	<?php
}
