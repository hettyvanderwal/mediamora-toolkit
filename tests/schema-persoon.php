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

require dirname( __DIR__ ) . '/modules/schema-persoon.php';

const PERSOON     = 'https://voorbeeld.nl/#persoon';
const ORGANISATIE = 'https://voorbeeld.nl/#organization';

$basis = array(
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

// Zoals Rank Math de data aanlevert in rank_math/json_ld.
$graph = array(
	'publisher' => array(
		'@type' => 'Organization',
		'@id'   => ORGANISATIE,
		'name'  => 'Therapiepraktijk Geertje Dokter',
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

// Gewoon geval.
$uit = mm_schema_persoon_toevoegen( $graph, $basis, PERSOON, ORGANISATIE, '', $bezet );
$p   = $uit['mm_persoon'] ?? array();
$test( 'Person-node erbij', 'Person' === ( $p['@type'] ?? '' ) && PERSOON === $p['@id'] && 'Geertje Dokter' === $p['name'] );
$test( 'jobTitle', 'Psychosociaal therapeut' === ( $p['jobTitle'] ?? '' ) );
$test( 'alumniOf', array( '@type' => 'EducationalOrganization', 'name' => 'Boswijk Instituut', 'url' => 'https://www.boswijkinstituut.nl' ) === ( $p['alumniOf'] ?? null ) );
$test( 'worksFor', array( '@id' => ORGANISATIE ) === ( $p['worksFor'] ?? null ) );
$test( 'founder op Organization', array( '@id' => PERSOON ) === ( $uit['publisher']['founder'] ?? null ) );
$test( 'geen employee', ! isset( $uit['publisher']['employee'] ) );
$test( 'lege velden weg', ! isset( $p['memberOf'] ) && ! isset( $p['sameAs'] ) && ! isset( $p['image'] ) && ! isset( $p['description'] ) && ! isset( $p['url'] ) );
$test( 'geen description op Organization', ! isset( $uit['publisher']['description'] ) );
$test( 'niet bezet', false === $bezet );
$test( 'WebSite ongemoeid', $graph['WebSite'] === $uit['WebSite'] );

// Alles ingevuld, als medewerker.
$alles = array_merge(
	$basis,
	array(
		'omschrijving' => 'Voormalig huisarts.',
		'url'          => 'https://voorbeeld.nl/over/',
		'sameas'       => array( 'https://www.linkedin.com/in/x/', 'https://nfg.nl/x' ),
		'lid_naam'     => 'Tekstnet',
		'rol'          => 'employee',
		'praktijk'     => 'Coaching voor artsen.',
	)
);
$uit = mm_schema_persoon_toevoegen( $graph, $alles, PERSOON, ORGANISATIE, 'https://voorbeeld.nl/foto.jpg' );
$p   = $uit['mm_persoon'];
$test( 'sameAs als lijst', array( 'https://www.linkedin.com/in/x/', 'https://nfg.nl/x' ) === $p['sameAs'] );
$test( 'memberOf zonder url', array( '@type' => 'Organization', 'name' => 'Tekstnet' ) === $p['memberOf'] );
$test( 'image', 'https://voorbeeld.nl/foto.jpg' === $p['image'] );
$test( 'description en url', 'Voormalig huisarts.' === $p['description'] && 'https://voorbeeld.nl/over/' === $p['url'] );
$test( 'employee in plaats van founder', array( '@id' => PERSOON ) === ( $uit['publisher']['employee'] ?? null ) && ! isset( $uit['publisher']['founder'] ) );
$test( 'praktijkomschrijving op Organization', 'Coaching voor artsen.' === $uit['publisher']['description'] );

// Opleiding-URL zonder naam telt niet.
$uit = mm_schema_persoon_toevoegen( $graph, array_merge( $basis, array( 'opleiding_naam' => '' ) ), PERSOON, ORGANISATIE, '' );
$test( 'geen alumniOf zonder naam', ! isset( $uit['mm_persoon']['alumniOf'] ) );

// Zonder naam niets.
$uit = mm_schema_persoon_toevoegen( $graph, array_merge( $basis, array( 'naam' => '' ) ), PERSOON, ORGANISATIE, '' );
$test( 'zonder naam niets', $graph === $uit );

// Zonder Organization niets.
$zonder = $graph;
unset( $zonder['publisher'] );
$test( 'zonder Organization niets', $zonder === mm_schema_persoon_toevoegen( $zonder, $basis, PERSOON, ORGANISATIE, '' ) );

// Publisher als Person (knowledgegraph_type person, of een ander @id): niets.
$persoon              = $graph;
$persoon['publisher'] = array( '@type' => array( 'Person', 'Organization' ), '@id' => 'https://voorbeeld.nl/#person', 'name' => 'X' );
$test( 'publisher is Person: niets', $persoon === mm_schema_persoon_toevoegen( $persoon, $basis, PERSOON, ORGANISATIE, '' ) );

// Organization onder een andere sleutel, met een subtype.
$ander = array( 'place' => array( '@type' => 'Place' ), 'org' => array( '@type' => array( 'Organization', 'ProfessionalService' ), '@id' => ORGANISATIE ) );
$uit   = mm_schema_persoon_toevoegen( $ander, $basis, PERSOON, ORGANISATIE, '' );
$test( 'Organization op @id gevonden', array( '@id' => PERSOON ) === ( $uit['org']['founder'] ?? null ) && isset( $uit['mm_persoon'] ) );

// Losse code met hetzelfde @id: niets dubbel.
$los               = $graph;
$los['losse_code'] = array( '@type' => 'Person', '@id' => PERSOON, 'name' => 'Oud' );
$uit               = mm_schema_persoon_toevoegen( $los, $basis, PERSOON, ORGANISATIE, '', $bezet );
$test( 'zelfde @id: niets', $los === $uit && true === $bezet );

// Losse code met een eigen @id, zoals #marianne-brouwers: founder staat er al.
$los                         = $graph;
$los['publisher']['founder'] = array( '@id' => 'https://voorbeeld.nl/#marianne-brouwers' );
$los['oud']                  = array( '@type' => 'Person', '@id' => 'https://voorbeeld.nl/#marianne-brouwers' );
$uit                         = mm_schema_persoon_toevoegen( $los, $basis, PERSOON, ORGANISATIE, '', $bezet );
$test( 'founder al gezet: niets', $los === $uit && true === $bezet );

$los                          = $graph;
$los['publisher']['employee'] = array( '@id' => 'https://voorbeeld.nl/#iemand' );
$test( 'employee al gezet: niets', $los === mm_schema_persoon_toevoegen( $los, $basis, PERSOON, ORGANISATIE, '' ) );

// Geen array.
$test( 'geen array', null === mm_schema_persoon_toevoegen( null, $basis, PERSOON, ORGANISATIE, '' ) );

// Geldige JSON met de tekens die Rank Math er ook doorheen haalt.
$uit = mm_schema_persoon_toevoegen( $graph, array_merge( $basis, array( 'omschrijving' => 'Zegt "hallo" & </script>' ) ), PERSOON, ORGANISATIE, '' );
$json = json_encode( array( '@context' => 'https://schema.org', '@graph' => array_values( $uit ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$test( 'geldige JSON', is_array( json_decode( $json, true ) ) );

exit( $fout ? 1 : 0 );
