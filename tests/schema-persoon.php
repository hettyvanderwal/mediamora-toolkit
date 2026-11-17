<?php
/**
 * Mediamora Toolkit, testgevallen voor Persoon in schema.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/schema-persoon.php
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

// Het modulebestand roept bij het laden alleen add_action en add_filter aan.
function add_action() {}
function add_filter() {}

// Eenvoudige versies van wat de module van WordPress gebruikt.
function sanitize_title( $tekst ) {
	$tekst = strtr( mb_strtolower( (string) $tekst ), array( 'é' => 'e', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u' ) );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $tekst ), '-' );
}
function sanitize_text_field( $tekst ) {
	return trim( strip_tags( (string) $tekst ) );
}
function esc_url_raw( $url ) {
	return preg_match( '#^https?://#', (string) $url ) ? (string) $url : '';
}

require dirname( __DIR__ ) . '/modules/schema-persoon.php';

const BASIS       = 'https://voorbeeld.nl/#persoon';
const ORGANISATIE = 'https://voorbeeld.nl/#organization';
const AUTEUR      = 'https://voorbeeld.nl/author/3c51dd7c9e2446b76abd4812b3edea35/';

const JSON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

// Zoals Rank Math de data aanlevert in rank_math/json_ld.
$graph = array(
	'publisher' => array(
		'@type'       => 'Organization',
		'@id'         => ORGANISATIE,
		'name'        => 'Praktijk',
		'description' => 'Van Rank Math.',
	),
	'WebSite'   => array(
		'@type'     => 'WebSite',
		'@id'       => 'https://voorbeeld.nl/#website',
		'publisher' => array( '@id' => ORGANISATIE ),
	),
);

$fout = 0;
$test = function ( $naam, $goed ) use ( &$fout ) {
	echo ( $goed ? 'goed ' : 'FOUT ' ), $naam, "\n";
	$fout += $goed ? 0 : 1;
};

/**
 * Doet wat mm_schema_persoon_json_ld() doet, zonder WordPress: de optie
 * omzetten, @id's geven, foto en auteurs erbij, en toevoegen.
 *
 * @param array $optie   Zoals in mm_schema_persoon.
 * @param array $auteurs Per volgnummer de auteur-@id's.
 */
$draai = function ( $data, $optie, $auteurs = array(), &$bezet = null ) {
	$instellingen = mm_schema_persoon_omzetten( $optie );
	$personen     = mm_schema_persoon_met_ids( $instellingen['personen'], BASIS );
	foreach ( $personen as $nr => $persoon ) {
		$personen[ $nr ]['foto']    = $persoon['foto'] ? 'https://voorbeeld.nl/foto-' . $persoon['foto'] . '.jpg' : '';
		$personen[ $nr ]['auteurs'] = $auteurs[ $nr ] ?? array();
	}
	return mm_schema_persoon_toevoegen( $data, $personen, $instellingen['praktijk'], ORGANISATIE, $bezet );
};

$persoon = function ( $velden ) {
	return array_merge( mm_schema_persoon_leeg(), $velden );
};

$personen_van = function ( $data ) {
	return array_values(
		array_filter(
			$data,
			function ( $node ) {
				return in_array( 'Person', (array) ( $node['@type'] ?? array() ), true );
			}
		)
	);
};


/* Omzetten van het oude formaat (1.20 t/m 1.23) ----------------------- */

$oud_basis = array(
	'naam'           => 'Geertje Dokter',
	'functie'        => 'Psychosociaal therapeut',
	'omschrijving'   => '',
	'url'            => '',
	'opleiding_naam' => 'Boswijk Instituut',
	'opleiding_url'  => 'https://www.boswijkinstituut.nl',
	'lid_naam'       => '',
	'lid_url'        => '',
	'sameas'         => array(),
	'foto'           => 0,
	'rol'            => 'founder',
	'praktijk'       => '',
);
$oud_alles = array(
	'naam'           => 'Marianne Brouwers',
	'functie'        => 'Coach voor artsen',
	'omschrijving'   => 'Zegt "hallo" & </script>',
	'url'            => 'https://voorbeeld.nl/over/',
	'opleiding_naam' => 'Universiteit',
	'opleiding_url'  => '',
	'lid_naam'       => 'Tekstnet',
	'lid_url'        => 'https://tekstnet.nl',
	'sameas'         => array( 'https://www.linkedin.com/in/x/', 'https://nfg.nl/x' ),
	'foto'           => 12,
	'rol'            => 'employee',
	'praktijk'       => 'Coaching voor artsen.',
);

// Uitvoer van versie 1.23.0 met dezelfde optie en graph, letterlijk.
$v123_basis = '[{"@type":"Organization","@id":"https://voorbeeld.nl/#organization","name":"Praktijk","description":"Van Rank Math.","founder":{"@id":"https://voorbeeld.nl/#persoon"}},{"@type":"WebSite","@id":"https://voorbeeld.nl/#website","publisher":{"@id":"https://voorbeeld.nl/#organization"}},{"@type":"Person","@id":"https://voorbeeld.nl/#persoon","name":"Geertje Dokter","jobTitle":"Psychosociaal therapeut","alumniOf":{"@type":"EducationalOrganization","name":"Boswijk Instituut","url":"https://www.boswijkinstituut.nl"},"worksFor":{"@id":"https://voorbeeld.nl/#organization"}}]';
$v123_alles = '[{"@type":"Organization","@id":"https://voorbeeld.nl/#organization","name":"Praktijk","description":"Coaching voor artsen.","employee":{"@id":"https://voorbeeld.nl/#persoon"}},{"@type":"WebSite","@id":"https://voorbeeld.nl/#website","publisher":{"@id":"https://voorbeeld.nl/#organization"}},{"@type":"Person","@id":"https://voorbeeld.nl/#persoon","name":"Marianne Brouwers","jobTitle":"Coach voor artsen","description":"Zegt \"hallo\" & </script>","url":"https://voorbeeld.nl/over/","image":"https://voorbeeld.nl/foto.jpg","alumniOf":{"@type":"EducationalOrganization","name":"Universiteit"},"memberOf":{"@type":"Organization","name":"Tekstnet","url":"https://tekstnet.nl"},"sameAs":["https://www.linkedin.com/in/x/","https://nfg.nl/x"],"worksFor":{"@id":"https://voorbeeld.nl/#organization"}}]';

$test( 'oud formaat: uitvoer gelijk aan 1.23.0', $v123_basis === json_encode( array_values( $draai( $graph, $oud_basis ) ), JSON ) );
$uit = $draai( $graph, $oud_alles );
$uit['mm_persoon']['image'] = str_replace( 'foto-12', 'foto', $uit['mm_persoon']['image'] );
$test( 'oud formaat, alles ingevuld: uitvoer gelijk aan 1.23.0', $v123_alles === json_encode( array_values( $uit ), JSON ) );

$om = mm_schema_persoon_omzetten( $oud_alles );
$p  = $om['personen'][0] ?? array();
$test( 'omzetten: één persoon', 1 === count( $om['personen'] ) );
$test( 'omzetten: praktijk apart', 'Coaching voor artsen.' === $om['praktijk'] );
$test( 'omzetten: velden over', 'Marianne Brouwers' === $p['naam'] && 'Coach voor artsen' === $p['functie'] && 12 === $p['foto'] && 'employee' === $p['rol'] && $oud_alles['sameas'] === $p['sameas'] && 'https://voorbeeld.nl/over/' === $p['url'] );
$test( 'omzetten: opleiding wordt eerste regel', array( array( 'naam' => 'Universiteit', 'url' => '' ) ) === $p['opleidingen'] );
$test( 'omzetten: lidmaatschap wordt eerste regel', array( array( 'naam' => 'Tekstnet', 'url' => 'https://tekstnet.nl' ) ) === $p['lidmaatschappen'] );
$test( 'omzetten: nieuwe velden leeg', '' === $p['titel_voor'] && array() === $p['registraties'] && array() === $p['vakgebieden'] && array() === $p['talen'] && 0 === $p['gebruiker'] );
$test( 'omzetten: geen oude sleutels meer', ! isset( $p['opleiding_naam'] ) && ! isset( $p['lid_url'] ) && ! isset( $p['praktijk'] ) );

$om = mm_schema_persoon_omzetten( array_merge( $oud_basis, array( 'opleiding_naam' => '', 'opleiding_url' => 'https://alleen-url.nl' ) ) );
$test( 'omzetten: losse URL zonder naam blijft bewaard', array( array( 'naam' => '', 'url' => 'https://alleen-url.nl' ) ) === $om['personen'][0]['opleidingen'] );
$test( 'losse URL zonder naam: geen alumniOf', ! isset( $draai( $graph, array_merge( $oud_basis, array( 'opleiding_naam' => '' ) ) )['mm_persoon']['alumniOf'] ) );

$test( 'omzetten van het nieuwe formaat verandert niets', mm_schema_persoon_omzetten( $om ) === $om );
$test( 'nog niets opgeslagen: geen personen', array( 'personen' => array(), 'praktijk' => '' ) === mm_schema_persoon_omzetten( false ) );
$test( 'nog niets opgeslagen: graph ongemoeid', $graph === $draai( $graph, false ) );
$test( 'oud formaat zonder naam: niets', $graph === $draai( $graph, array_merge( $oud_basis, array( 'naam' => '' ) ) ) );


/* Eén persoon, de gewone velden ---------------------------------------- */

$uit = $draai( $graph, array( 'personen' => array( $persoon( array( 'naam' => 'Geertje Dokter', 'functie' => 'Psychosociaal therapeut' ) ) ) ), array(), $bezet );
$p   = $uit['mm_persoon'] ?? array();
$test( 'Person-node erbij', 'Person' === ( $p['@type'] ?? '' ) && BASIS === $p['@id'] && 'Geertje Dokter' === $p['name'] );
$test( 'worksFor', array( '@id' => ORGANISATIE ) === ( $p['worksFor'] ?? null ) );
$test( 'founder als enkele verwijzing', array( '@id' => BASIS ) === ( $uit['publisher']['founder'] ?? null ) );
$test( 'geen employee', ! isset( $uit['publisher']['employee'] ) );
$test( 'lege velden weg', array( '@type', '@id', 'name', 'jobTitle', 'worksFor' ) === array_keys( $p ) );
$test( 'description van Rank Math blijft', 'Van Rank Math.' === $uit['publisher']['description'] );
$test( 'niet bezet', false === $bezet );
$test( 'WebSite ongemoeid', $graph['WebSite'] === $uit['WebSite'] );


/* Meerdere personen ---------------------------------------------------- */

$team = array(
	'personen' => array(
		$persoon( array( 'naam' => 'Anna de Vries' ) ),
		$persoon( array( 'naam' => '' ) ),
		$persoon( array( 'naam' => 'Joëlle Bakker', 'rol' => 'employee' ) ),
		$persoon( array( 'naam' => 'Jan Jansen', 'rol' => 'employee' ) ),
		$persoon( array( 'naam' => 'Jan Jansen', 'rol' => 'employee' ) ),
	),
);
$uit = $draai( $graph, $team );
$ps  = $personen_van( $uit );
$test( 'vier personen, de lege valt weg', 4 === count( $ps ) );
$test( '@id\'s: eerste #persoon, dan op naam, uniek', array( BASIS, BASIS . '-joelle-bakker', BASIS . '-jan-jansen', BASIS . '-jan-jansen-2' ) === array_column( $ps, '@id' ) );
$test( 'elke persoon worksFor', 4 === count( array_filter( array_column( $ps, 'worksFor' ), function ( $w ) { return array( '@id' => ORGANISATIE ) === $w; } ) ) );
$test( 'founder enkel bij één oprichter', array( '@id' => BASIS ) === $uit['publisher']['founder'] );
$test( 'employee als lijst bij meer medewerkers', array( array( '@id' => BASIS . '-joelle-bakker' ), array( '@id' => BASIS . '-jan-jansen' ), array( '@id' => BASIS . '-jan-jansen-2' ) ) === $uit['publisher']['employee'] );
$test( 'sleutels mm_persoon, mm_persoon_2 ...', isset( $uit['mm_persoon'], $uit['mm_persoon_2'], $uit['mm_persoon_3'], $uit['mm_persoon_4'] ) );

$uit = $draai( $graph, array( 'personen' => array( $persoon( array( 'naam' => 'A' ) ), $persoon( array( 'naam' => 'B' ) ) ) ) );
$test( 'founder als lijst bij twee oprichters', array( array( '@id' => BASIS ), array( '@id' => BASIS . '-b' ) ) === $uit['publisher']['founder'] && ! isset( $uit['publisher']['employee'] ) );

$uit = $draai( $graph, array( 'personen' => array( $persoon( array( 'naam' => '' ) ), $persoon( array( 'naam' => 'Tweede' ) ) ) ) );
$test( 'eerste zonder naam: de volgende krijgt #persoon', BASIS === $uit['mm_persoon']['@id'] && 'Tweede' === $uit['mm_persoon']['name'] );

$uit = $draai( $graph, array( 'personen' => array( $persoon( array( 'naam' => 'A' ) ), $persoon( array( 'naam' => '!!!' ) ) ) ) );
$test( 'naam zonder letters: volgnummer als slug', BASIS . '-2' === $uit['mm_persoon_2']['@id'] );


/* Nieuwe velden -------------------------------------------------------- */

$alles = $persoon(
	array(
		'naam'            => 'Karin Smit',
		'titel_voor'      => 'dr.',
		'titel_na'        => 'RA',
		'opleidingen'     => array( array( 'naam' => 'Universiteit Utrecht', 'url' => 'https://www.uu.nl' ), array( 'naam' => 'NBA', 'url' => '' ), array( 'naam' => '', 'url' => 'https://weg.nl' ) ),
		'lidmaatschappen' => array( array( 'naam' => 'KNGF', 'url' => 'https://www.kngf.nl' ), array( 'naam' => 'NVFB', 'url' => '' ) ),
		'registraties'    => array(
			array( 'naam' => 'BIG-registratie 12345678901', 'erkend' => 'BIG-register', 'url' => 'https://www.bigregister.nl' ),
			array( 'naam' => 'Registeraccountant', 'erkend' => 'NBA', 'url' => '' ),
			array( 'naam' => 'Kwaliteitsregister', 'erkend' => '', 'url' => 'https://telt-niet.nl' ),
			array( 'naam' => '', 'erkend' => 'Zonder naam', 'url' => '' ),
		),
		'vakgebieden'     => array( 'Burn-out', 'Arts-patiëntcommunicatie' ),
		'talen'           => array( 'nl', 'en' ),
		'sameas'          => array( 'https://www.linkedin.com/in/k/' ),
		'foto'            => 7,
	)
);
$p = $draai( $graph, array( 'personen' => array( $alles ) ) )['mm_persoon'];
$test( 'honorificPrefix en -Suffix', 'dr.' === $p['honorificPrefix'] && 'RA' === $p['honorificSuffix'] );
$test( 'alumniOf als lijst, regel zonder naam weg', array( array( '@type' => 'EducationalOrganization', 'name' => 'Universiteit Utrecht', 'url' => 'https://www.uu.nl' ), array( '@type' => 'EducationalOrganization', 'name' => 'NBA' ) ) === $p['alumniOf'] );
$test( 'memberOf als lijst', array( array( '@type' => 'Organization', 'name' => 'KNGF', 'url' => 'https://www.kngf.nl' ), array( '@type' => 'Organization', 'name' => 'NVFB' ) ) === $p['memberOf'] );
$test(
	'hasCredential',
	array(
		array( '@type' => 'EducationalOccupationalCredential', 'name' => 'BIG-registratie 12345678901', 'recognizedBy' => array( '@type' => 'Organization', 'name' => 'BIG-register', 'url' => 'https://www.bigregister.nl' ) ),
		array( '@type' => 'EducationalOccupationalCredential', 'name' => 'Registeraccountant', 'recognizedBy' => array( '@type' => 'Organization', 'name' => 'NBA' ) ),
		array( '@type' => 'EducationalOccupationalCredential', 'name' => 'Kwaliteitsregister' ),
	) === $p['hasCredential']
);
$test( 'knowsAbout', array( 'Burn-out', 'Arts-patiëntcommunicatie' ) === $p['knowsAbout'] );
$test( 'knowsLanguage', array( 'nl', 'en' ) === $p['knowsLanguage'] );
$test( 'image', 'https://voorbeeld.nl/foto-7.jpg' === $p['image'] );
$test( 'volgorde van de eigenschappen', array( '@type', '@id', 'name', 'honorificPrefix', 'honorificSuffix', 'image', 'alumniOf', 'memberOf', 'hasCredential', 'knowsAbout', 'knowsLanguage', 'sameAs', 'worksFor' ) === array_keys( $p ) );

$p = $draai( $graph, array( 'personen' => array( $persoon( array( 'naam' => 'X', 'registraties' => array( array( 'naam' => 'BIG', 'erkend' => 'CIBG', 'url' => '' ) ) ) ) ) ) )['mm_persoon'];
$test( 'één registratie: geen lijst', array( '@type' => 'EducationalOccupationalCredential', 'name' => 'BIG', 'recognizedBy' => array( '@type' => 'Organization', 'name' => 'CIBG' ) ) === $p['hasCredential'] );


/* Koppeling met de blogschrijver --------------------------------------- */

// Een blogbericht zoals Rank Math het aanlevert, met de auteur onder ProfilePage.
$blog                = $graph;
$blog['WebPage']     = array( '@type' => 'WebPage', '@id' => 'https://voorbeeld.nl/blog/#webpage', 'author' => array( '@id' => AUTEUR ) );
$blog['ProfilePage'] = array( '@type' => 'Person', '@id' => AUTEUR, 'name' => 'M. Brouwers', 'url' => AUTEUR, 'worksFor' => array( '@id' => ORGANISATIE ) );
$blog['richSnippet'] = array(
	'@type'    => 'BlogPosting',
	'@id'      => 'https://voorbeeld.nl/blog/#richSnippet',
	'author'   => array( '@id' => AUTEUR, 'name' => 'M. Brouwers' ),
	'review'   => array( 'author' => array( array( '@id' => AUTEUR ) ) ),
	'isPartOf' => array( '@id' => 'https://voorbeeld.nl/blog/#webpage' ),
);
$marianne = array( 'personen' => array( $persoon( array( 'naam' => 'Marianne Brouwers', 'gebruiker' => 3 ) ) ) );

$uit = $draai( $blog, $marianne, array( array( AUTEUR ) ), $bezet );
$ps  = $personen_van( $uit );
$test( 'blog: precies één Person over', 1 === count( $ps ) && BASIS === $ps[0]['@id'] );
$test( 'blog: auteur-Person van Rank Math weg', ! isset( $uit['ProfilePage'] ) );
$test( 'blog: author van BlogPosting naar #persoon, met de naam', array( '@id' => BASIS, 'name' => 'Marianne Brouwers' ) === $uit['richSnippet']['author'] );
$test( 'blog: ook andere en diepere verwijzingen om', array( '@id' => BASIS ) === $uit['WebPage']['author'] && array( array( '@id' => BASIS ) ) === $uit['richSnippet']['review']['author'] );
$test( 'blog: niets meer naar het auteurs-@id', false === strpos( json_encode( $uit, JSON ), AUTEUR ) );
$test( 'blog: founder naar #persoon', array( '@id' => BASIS ) === $uit['publisher']['founder'] );
$test( 'blog: rest ongemoeid', $blog['richSnippet']['isPartOf'] === $uit['richSnippet']['isPartOf'] && $blog['WebSite'] === $uit['WebSite'] && false === $bezet );

// Auteursarchieven uit in Rank Math: @id is de pagina-URL met #author.
$hekje                         = $blog;
$hekje['ProfilePage']['@id']   = 'https://voorbeeld.nl/blog/#author';
$hekje['richSnippet']['author'] = array( '@id' => 'https://voorbeeld.nl/blog/#author', 'name' => 'M. Brouwers' );
unset( $hekje['WebPage']['author'], $hekje['richSnippet']['review'] );
$uit = $draai( $hekje, $marianne, array( array( AUTEUR, 'https://voorbeeld.nl/blog/#author' ) ) );
$test( '#author: één Person, author om', 1 === count( $personen_van( $uit ) ) && BASIS === $uit['richSnippet']['author']['@id'] );

// Tweede persoon gekoppeld.
$duo = array( 'personen' => array( $persoon( array( 'naam' => 'Anna' ) ), $persoon( array( 'naam' => 'Marianne Brouwers', 'gebruiker' => 3 ) ) ) );
$uit = $draai( $blog, $duo, array( 1 => array( AUTEUR ) ) );
$test( 'tweede persoon gekoppeld: author naar haar eigen @id', BASIS . '-marianne-brouwers' === $uit['richSnippet']['author']['@id'] && 2 === count( $personen_van( $uit ) ) );

// Bericht van iemand anders: die auteur blijft staan.
$uit = $draai( $blog, $marianne, array( array( 'https://voorbeeld.nl/author/iemand-anders/' ) ) );
$test( 'andere schrijver: Person van Rank Math blijft', $blog['ProfilePage'] === $uit['ProfilePage'] && AUTEUR === $uit['richSnippet']['author']['@id'] );

// Niet gekoppeld: alles zoals het was, plus onze persoon.
$uit = $draai( $blog, array( 'personen' => array( $persoon( array( 'naam' => 'Marianne Brouwers' ) ) ) ) );
$test( 'niet gekoppeld: Person van Rank Math blijft', isset( $uit['ProfilePage'] ) && 2 === count( $personen_van( $uit ) ) );

// Auteursarchief: de ProfilePage krijgt mainEntity naar de persoon.
$archief                = $graph;
$archief['WebPage']     = array( '@type' => 'ProfilePage', '@id' => AUTEUR . '#webpage' );
$archief['ProfilePage'] = array( '@type' => 'Person', '@id' => AUTEUR, 'name' => 'M. Brouwers', 'mainEntityOfPage' => array( '@id' => AUTEUR . '#webpage' ) );
$uit = $draai( $archief, $marianne, array( array( AUTEUR ) ) );
$test( 'auteursarchief: één Person, ProfilePage met mainEntity', 1 === count( $personen_van( $uit ) ) && array( '@id' => BASIS ) === ( $uit['WebPage']['mainEntity'] ?? null ) );

// Pagina zonder verwijzing naar de auteur (zoals /over/): Rank Math haalt
// de ProfilePage daar later zelf weg; de module doet dat nu al.
$pagina = $graph;
$pagina['ProfilePage'] = $blog['ProfilePage'];
$uit = $draai( $pagina, $marianne, array( array( AUTEUR ) ) );
$zonder = $draai( $graph, $marianne );
$test( 'pagina zonder verwijzing: zelfde als zonder auteur', $zonder === $uit );


/* Bezet-controle ------------------------------------------------------- */

$los               = $graph;
$los['losse_code'] = array( '@type' => 'Person', '@id' => BASIS, 'name' => 'Oud' );
$test( 'zelfde @id: niets', $los === $draai( $los, $team, array(), $bezet ) && true === $bezet );

$los               = $graph;
$los['losse_code'] = array( '@type' => 'Person', '@id' => BASIS . '-jan-jansen', 'name' => 'Oud' );
$test( '@id van een volgende persoon: niets', $los === $draai( $los, $team, array(), $bezet ) && true === $bezet );

$los                         = $blog;
$los['publisher']['founder'] = array( '@id' => 'https://voorbeeld.nl/#marianne-brouwers' );
$los['oud']                  = array( '@type' => 'Person', '@id' => 'https://voorbeeld.nl/#marianne-brouwers' );
$test( 'founder al gezet: niets, ook geen auteurskoppeling', $los === $draai( $los, $marianne, array( array( AUTEUR ) ), $bezet ) && true === $bezet );

$los                          = $graph;
$los['publisher']['employee'] = array( '@id' => 'https://voorbeeld.nl/#iemand' );
$test( 'employee al gezet: niets', $los === $draai( $los, $marianne, array(), $bezet ) && true === $bezet );


/* Wat er niet is ------------------------------------------------------- */

$zonder = $graph;
unset( $zonder['publisher'] );
$test( 'zonder Organization niets', $zonder === $draai( $zonder, $marianne ) );

$persoon_site              = $graph;
$persoon_site['publisher'] = array( '@type' => array( 'Person', 'Organization' ), '@id' => 'https://voorbeeld.nl/#person', 'name' => 'X' );
$test( 'publisher is Person: niets', $persoon_site === $draai( $persoon_site, $marianne ) );

$ander = array( 'place' => array( '@type' => 'Place' ), 'org' => array( '@type' => array( 'Organization', 'ProfessionalService' ), '@id' => ORGANISATIE ) );
$uit   = $draai( $ander, $marianne );
$test( 'Organization op @id gevonden', array( '@id' => BASIS ) === ( $uit['org']['founder'] ?? null ) && isset( $uit['mm_persoon'] ) );

$test( 'geen array', null === $draai( null, $marianne ) );


/* Tekstvakken in het formulier ----------------------------------------- */

$rijen = mm_schema_persoon_regels_delen( "BIG-registratie | BIG-register | https://www.bigregister.nl\n\n  Registeraccountant |NBA \nKwaliteitsregister\n | | geen-url", array( 'naam', 'erkend', 'url' ) );
$test(
	'regels "Naam | Erkend door | URL" lezen',
	array(
		array( 'naam' => 'BIG-registratie', 'erkend' => 'BIG-register', 'url' => 'https://www.bigregister.nl' ),
		array( 'naam' => 'Registeraccountant', 'erkend' => 'NBA', 'url' => '' ),
		array( 'naam' => 'Kwaliteitsregister', 'erkend' => '', 'url' => '' ),
	) === $rijen
);
$test( 'en terug naar tekst', "BIG-registratie | BIG-register | https://www.bigregister.nl\nRegisteraccountant | NBA\nKwaliteitsregister" === mm_schema_persoon_delen_tekst( $rijen ) );
$test( 'URL met | in de naam blijft bij de laatste kolom', array( array( 'naam' => 'A', 'url' => 'https://x.nl/?a|b' ) ) === mm_schema_persoon_regels_delen( 'A | https://x.nl/?a|b', array( 'naam', 'url' ) ) );
$test( 'lege tussenkolom blijft staan', 'A |  | https://x.nl' === mm_schema_persoon_delen_tekst( array( array( 'naam' => 'A', 'erkend' => '', 'url' => 'https://x.nl' ) ) ) );


/* Geldige JSON --------------------------------------------------------- */

$uit  = $draai( $blog, array( 'personen' => array( $alles, $persoon( array( 'naam' => 'Zegt "hallo" & </script>', 'gebruiker' => 3 ) ) ) ), array( 1 => array( AUTEUR ) ) );
$json = json_encode( array( '@context' => 'https://schema.org', '@graph' => array_values( $uit ) ), JSON );
$test( 'geldige JSON', is_array( json_decode( $json, true ) ) );

exit( $fout ? 1 : 0 );
