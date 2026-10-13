<?php
/**
 * Mediamora Toolkit, module: Elementor, oude versies tegenhouden
 * Wordt alleen geladen als de module aanstaat.
 *
 * Aanleiding: op sensilief.nl stond een template in twee tabbladen open. Het
 * template werd intussen server-side aangepast, waarna opslaan in het oudste
 * tabblad zowel die wijziging als het werk uit het andere tabblad overschreef.
 * Elementor kijkt bij opslaan niet of het document sinds het laden is
 * veranderd, en de post lock van WordPress helpt hier niet: die vervalt na
 * 150 seconden zonder heartbeat, geldt niet tussen tabbladen van dezelfde
 * gebruiker en wordt door Novamira, REST en WP-CLI niet gezet of gecontroleerd.
 *
 * Werkwijze (optimistische concurrency):
 *
 * 1. Bij het laden in de editor krijgt elk document een vingerafdruk mee in
 *    zijn config (mm_vingerafdruk): een hash over de ruwe waarden van
 *    _elementor_data en _elementor_page_settings, rechtstreeks uit de
 *    database, dus buiten de object cache om. Dat geldt voor elk document dat
 *    de editor laadt (pagina, bericht, template, popup, header, footer, kit),
 *    want ze lopen allemaal via Document::get_config() en het filter
 *    elementor/document/config.
 *
 * 2. Het script in de editor stuurt bij elke eigen aanvraag (jQuery-ajax en
 *    fetch, alleen naar dezelfde origin) de header X-MM-Vingerafdruk mee, met
 *    per geladen document id:hash.
 *
 * 3. Bij save_builder van het document zelf vergelijkt de module die waarde
 *    in elementor/document/before_save met de database. Wijkt hij af, dan
 *    volgt een exception. Die komt vóór elke schrijfactie; de ajax-module van
 *    Elementor vangt hem op en stuurt hem als fout terug naar de editor.
 *    Stuurt een tabblad geen vingerafdruk mee (bijvoorbeeld omdat het al open
 *    stond voordat de module aanging), dan wordt het nooit tegengehouden.
 *
 * 4. Doorschuiven. Schrijft een aanvraag van het tabblad zelf naar een
 *    document, en kwam de database vlak daarvoor nog overeen met wat het
 *    tabblad kende, dan krijgt het tabblad na elke schrijfactie de nieuwe
 *    vingerafdruk terug in de response-header. Dat dekt de gewone opslag
 *    (Elementor kan bij opslaan nog normaliseren), maar ook schrijfacties
 *    naast de opslag: een globale kleur of typografie via REST naar de kit,
 *    het opruimen van verwijderde global classes, en hooks als save_post die
 *    in dezelfde aanvraag nog eens schrijven. Meerdere schrijfacties in één
 *    aanvraag ketenen: elke volgende vergelijkt met de waarde na de vorige.
 *    Een ander tabblad kent de oude waarde en wordt dus wel tegengehouden.
 *
 * Autosave: schrijft Elementor naar een autosave-revisie (document
 * gepubliceerd of privé, ook bij Voorbeeld), dan blijft het document zelf
 * ongemoeid en controleert de module niets. Bij een concept of een document
 * in afwachting schrijft Elementor de autosave in het document zelf; die
 * wordt dus wel gecontroleerd, anders overschrijft een oud tabblad elke
 * minuut ongemerkt het werk van een ander.
 *
 * Wijzigingen buiten de editor (Novamira, REST, WP-CLI, update_post_meta)
 * worden niet tegengehouden, maar veranderen de vingerafdruk, zodat het
 * volgende opslaan vanuit een al geopend tabblad wordt geweigerd.
 *
 * Slaat niets op: geen opties, meta of tabellen, dus ook niets op te ruimen
 * in uninstall.php. Op de voorkant doet de module niets; de schrijfhooks
 * komen er alleen bij als een aanvraag de header meestuurt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_ELEMENTOR_VERSIE_SLEUTELS = array( '_elementor_data', '_elementor_page_settings' );

function mm_elementor_versie_tekst() {
	return 'Deze pagina is intussen aangepast, door iemand anders of in een ander tabblad. Herlaad de pagina voordat je opslaat. Wat je nu nog niet hebt opgeslagen, kun je eerst kopiëren.';
}

/**
 * De vingerafdruk van een document, uit de database.
 *
 * Over de ruwe meta_value, dus zonder unserialize of json_decode: wat er
 * byte voor byte staat. Lukt de query niet, dan een lege string, en een lege
 * string blokkeert nooit.
 *
 * @param int $post_id Main-ID van het document.
 * @return string md5 in hex, of '' als het niet te bepalen is.
 */
function mm_elementor_versie_vingerafdruk( $post_id ) {
	global $wpdb;

	$rijen = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ( '_elementor_data', '_elementor_page_settings' ) ORDER BY meta_key, meta_id",
			$post_id
		),
		ARRAY_N
	);

	if ( $wpdb->last_error || ! is_array( $rijen ) ) {
		return '';
	}

	$context = hash_init( 'md5' );
	foreach ( $rijen as $rij ) {
		hash_update( $context, $rij[0] . "\0" . strlen( (string) $rij[1] ) . "\0" . $rij[1] );
	}
	return hash_final( $context );
}

/**
 * Leest id:hash,id:hash uit een header. Alles wat er niet precies zo uitziet
 * valt weg.
 *
 * @param string $kop
 * @return string[] Hash per document-ID.
 */
function mm_elementor_versie_kop_lezen( $kop ) {
	$bekend = array();
	foreach ( explode( ',', (string) $kop ) as $deel ) {
		if ( preg_match( '/^\s*(\d+):([a-f0-9]{32})\s*$/', $deel, $m ) ) {
			$bekend[ (int) $m[1] ] = $m[2];
		}
	}
	return $bekend;
}

/**
 * De toestand van deze aanvraag.
 *
 * bekend:    per document de vingerafdruk die het tabblad kent, na
 *            doorschuiven bijgewerkt.
 * mee:       per document true (doorschuiven) of false (niet), bepaald bij
 *            de eerste schrijfactie of controle in deze aanvraag.
 * gewijzigd: wat in de response-header terug moet.
 */
function &mm_elementor_versie_staat() {
	static $staat = array(
		'bekend'    => array(),
		'mee'       => array(),
		'gewijzigd' => array(),
	);
	return $staat;
}

/**
 * Begint de bewaking voor deze aanvraag met de vingerafdrukken uit de
 * header, en hangt de schrijfhooks erbij. Los aan te roepen om te testen.
 *
 * @param string $kop Waarde van X-MM-Vingerafdruk.
 */
function mm_elementor_versie_start( $kop ) {
	$staat              = &mm_elementor_versie_staat();
	$staat['bekend']    = mm_elementor_versie_kop_lezen( $kop );
	$staat['mee']       = array();
	$staat['gewijzigd'] = array();

	if ( ! $staat['bekend'] || has_action( 'updated_post_meta', 'mm_elementor_versie_na_schrijven' ) ) {
		return;
	}

	// Vóór het schrijven: bepalen of dit document doorschuift.
	add_action( 'add_post_meta', 'mm_elementor_versie_voor_toevoegen', 10, 2 );
	add_action( 'update_post_meta', 'mm_elementor_versie_voor_bijwerken', 10, 3 );
	add_action( 'delete_post_meta', 'mm_elementor_versie_voor_bijwerken', 10, 3 );

	// Na het schrijven: nieuwe vingerafdruk terug naar het tabblad.
	add_action( 'added_post_meta', 'mm_elementor_versie_na_schrijven', 10, 3 );
	add_action( 'updated_post_meta', 'mm_elementor_versie_na_schrijven', 10, 3 );
	add_action( 'deleted_post_meta', 'mm_elementor_versie_na_schrijven', 10, 3 );
}

// $_SERVER is door WordPress geslasht, maar de waarde bevat alleen cijfers,
// hex, dubbele punten en komma's; de rest valt weg in kop_lezen.
if ( ! empty( $_SERVER['HTTP_X_MM_VINGERAFDRUK'] ) ) {
	mm_elementor_versie_start( (string) $_SERVER['HTTP_X_MM_VINGERAFDRUK'] );
}

/**
 * @param int    $post_id
 * @param string $meta_key
 */
function mm_elementor_versie_voor_toevoegen( $post_id, $meta_key ) {
	mm_elementor_versie_voor( $post_id, $meta_key );
}

/**
 * Voor update_post_meta en delete_post_meta, die de meta-ID('s) voorop hebben.
 *
 * @param int|int[] $meta_id
 * @param int       $post_id
 * @param string    $meta_key
 */
function mm_elementor_versie_voor_bijwerken( $meta_id, $post_id, $meta_key ) {
	mm_elementor_versie_voor( $post_id, $meta_key );
}

/**
 * Eerste schrijfactie op een bekend document in deze aanvraag: schuift het
 * door? Alleen als de database nu nog precies is wat het tabblad kent. Wat
 * hier besloten wordt, geldt voor de rest van de aanvraag; zo ketenen
 * volgende schrijfacties gewoon door.
 *
 * @param int    $post_id
 * @param string $meta_key
 */
function mm_elementor_versie_voor( $post_id, $meta_key ) {
	if ( ! in_array( $meta_key, MM_ELEMENTOR_VERSIE_SLEUTELS, true ) ) {
		return;
	}

	$staat   = &mm_elementor_versie_staat();
	$post_id = (int) $post_id;

	if ( ! isset( $staat['bekend'][ $post_id ] ) || isset( $staat['mee'][ $post_id ] ) ) {
		return;
	}

	$staat['mee'][ $post_id ] = mm_elementor_versie_vingerafdruk( $post_id ) === $staat['bekend'][ $post_id ];
}

/**
 * @param int|int[] $meta_id
 * @param int       $post_id
 * @param string    $meta_key
 */
function mm_elementor_versie_na_schrijven( $meta_id, $post_id, $meta_key ) {
	if ( ! in_array( $meta_key, MM_ELEMENTOR_VERSIE_SLEUTELS, true ) ) {
		return;
	}

	$staat   = &mm_elementor_versie_staat();
	$post_id = (int) $post_id;

	if ( empty( $staat['mee'][ $post_id ] ) ) {
		return;
	}

	mm_elementor_versie_doorschuiven( $post_id );
}

/**
 * Leest de vingerafdruk opnieuw uit de database, onthoudt hem voor de rest
 * van de aanvraag en zet hem in de response-header.
 *
 * @param int $post_id
 * @return string De nieuwe vingerafdruk, of '' als die niet te bepalen was.
 */
function mm_elementor_versie_doorschuiven( $post_id ) {
	$hash = mm_elementor_versie_vingerafdruk( $post_id );
	if ( '' === $hash ) {
		return '';
	}

	$staat                          = &mm_elementor_versie_staat();
	$staat['bekend'][ $post_id ]    = $hash;
	$staat['gewijzigd'][ $post_id ] = $hash;

	if ( ! headers_sent() ) {
		$delen = array();
		foreach ( $staat['gewijzigd'] as $id => $waarde ) {
			$delen[] = $id . ':' . $waarde;
		}
		header( 'X-MM-Vingerafdruk: ' . implode( ',', $delen ), true );
	}

	return $hash;
}

/**
 * Komt deze save van de knop of autosave in de editor, voor het document dat
 * daar open staat? Alleen dan mag er geweigerd worden. Saves van andere
 * documenten binnen dezelfde aanvraag (zoals Elementor Pro dat kan doen)
 * schuiven alleen door.
 *
 * @param int $post_id
 */
function mm_elementor_versie_editor_save( $post_id ) {
	if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) || 'elementor_ajax' !== $_REQUEST['action'] ) {
		return false;
	}

	if ( ! isset( $_REQUEST['editor_post_id'] ) || absint( $_REQUEST['editor_post_id'] ) !== $post_id ) {
		return false;
	}

	$elementor = \Elementor\Plugin::$instance;
	$ajax      = isset( $elementor->common ) && $elementor->common ? $elementor->common->get_component( 'ajax' ) : null;
	$actie     = $ajax && method_exists( $ajax, 'get_current_action_data' ) ? $ajax->get_current_action_data() : false;

	return is_array( $actie ) && isset( $actie['action'] ) && 'save_builder' === $actie['action'];
}

add_filter( 'elementor/document/config', 'mm_elementor_versie_config', 10, 2 );

/**
 * @param array $config  Extra config van het document.
 * @param int   $post_id Main-ID, ook als de editor de autosave laadt: de
 *                       opslag schrijft altijd naar het document zelf.
 */
function mm_elementor_versie_config( $config, $post_id ) {
	$hash = mm_elementor_versie_vingerafdruk( (int) $post_id );
	if ( '' !== $hash && is_array( $config ) ) {
		$config['mm_vingerafdruk'] = $hash;
	}
	return $config;
}

// Zo vroeg mogelijk, vóór andere code die op before_save iets doet.
add_action( 'elementor/document/before_save', 'mm_elementor_versie_controleren', -1000, 2 );

/**
 * @param \Elementor\Core\Base\Document $document
 * @param array                         $data
 * @throws \Exception Als het document sinds het laden in dit tabblad is veranderd.
 */
function mm_elementor_versie_controleren( $document, $data ) {
	if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
		return;
	}

	$post_id = (int) $document->get_main_id();
	$post    = $document->get_post();

	// Een autosave-revisie heeft een eigen post-ID; het document zelf blijft
	// dan ongemoeid.
	if ( ! $post || (int) $post->ID !== $post_id ) {
		return;
	}

	$staat = &mm_elementor_versie_staat();

	if ( ! isset( $staat['bekend'][ $post_id ] ) || ! mm_elementor_versie_editor_save( $post_id ) ) {
		return;
	}

	$database = mm_elementor_versie_vingerafdruk( $post_id );
	if ( '' === $database ) {
		return;
	}

	$geladen = $staat['bekend'][ $post_id ];

	if ( $database === $geladen ) {
		$staat['mee'][ $post_id ] = true;
		return;
	}

	$staat['mee'][ $post_id ] = false;

	$soort = isset( $data['settings']['post_status'] ) && 'autosave' === $data['settings']['post_status'] ? 'auto' : 'opslaan';
	if ( ! headers_sent() ) {
		header( 'X-MM-Vingerafdruk-Geweigerd: ' . $post_id . ':' . $geladen . ':' . $database . ':' . $soort );
	}

	throw new \Exception( mm_elementor_versie_tekst(), 409 );
}

add_filter( 'elementor/documents/ajax_save/return_data', 'mm_elementor_versie_terug', 10, 2 );

/**
 * Na een geslaagde opslag de vingerafdruk opnieuw uit de database. Elementor
 * zet config.document alleen in het tabblad als het opgeslagen document het
 * huidige is; het script leest daarom ook de header.
 *
 * Bij een autosave-revisie is het document zelf niet geschreven en staat mee
 * niet aan, dus dan blijft alles zoals het was.
 *
 * @param array                         $return_data
 * @param \Elementor\Core\Base\Document $document
 */
function mm_elementor_versie_terug( $return_data, $document ) {
	if ( ! is_array( $return_data ) || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
		return $return_data;
	}

	$post_id = (int) $document->get_main_id();
	$staat   = &mm_elementor_versie_staat();

	if ( empty( $staat['mee'][ $post_id ] ) ) {
		return $return_data;
	}

	$hash = mm_elementor_versie_doorschuiven( $post_id );
	if ( '' !== $hash ) {
		$return_data['config']['document']['mm_vingerafdruk'] = $hash;
	}

	return $return_data;
}

add_action( 'elementor/editor/after_enqueue_scripts', 'mm_elementor_versie_script' );

function mm_elementor_versie_script() {
	wp_register_script( 'mm-toolkit-elementor-versie', false, array( 'jquery' ), MM_TOOLKIT_VERSIE, true );
	wp_enqueue_script( 'mm-toolkit-elementor-versie' );
	wp_add_inline_script( 'mm-toolkit-elementor-versie', str_replace( '__TEKST__', wp_json_encode( mm_elementor_versie_tekst() ), mm_elementor_versie_js() ) );
}

/**
 * Het script in de editor.
 *
 * - Leest de vingerafdrukken uit de config van de geladen documenten
 *   (elementor.documents), zodat een document dat opnieuw wordt geladen
 *   vanzelf de verse waarde heeft.
 * - Zet ze in de header van elke eigen aanvraag. jQuery via ajaxPrefilter:
 *   de callback daar loopt vóór de success-callback van Elementor, zodat de
 *   nieuwe waarde er al staat als Elementor verdergaat. fetch (REST en
 *   apiFetch) via een omhulsel.
 * - De kit neemt een doorgeschoven vingerafdruk niet zomaar over. Een
 *   globale kleur of typografie via REST schrijft de kit in de database bij,
 *   maar alleen het commando globals/.../create zet het item ook in het
 *   kitdocument in het geheugen (hook KitGlobalsUpdateColors en -Typography,
 *   alleen met Site-instellingen open). Gaat het buiten dat commando om, zoals
 *   met een kale $e.data.create, dan mist het geheugen het item en zou
 *   opslaan van de kit het stil overschrijven. Daarom wacht de nieuwe waarde
 *   tot het item met dat _id in custom_colors of custom_typography van de
 *   geladen kit staat; zo niet, dan blijft de oude waarde staan en wordt
 *   opslaan van de kit geweigerd. Andere schrijfacties naar de kit buiten
 *   zijn eigen opslag schuiven niet door. De eigen opslag van de kit komt
 *   binnen via config.document in het antwoord, dat Elementor zelf in de
 *   config zet (de kit is dan het huidige document).
 * - Een geweigerde opslag: een dialoog van Elementor met de melding, de
 *   toast met dezelfde tekst blijft dan weg. Een geweigerde autosave meldt
 *   het per document één keer. In de console staan de vingerafdrukken.
 */
function mm_elementor_versie_js() {
	return <<<'JS'
(function ($) {
	if (window.mmElementorVersie || !$) return;
	window.mmElementorVersie = true;

	var TEKST = __TEKST__, KOP = 'X-MM-Vingerafdruk', GEWEIGERD = 'X-MM-Vingerafdruk-Geweigerd';
	var gemeld = {}, dialoog = null, wachtend = null;

	function documenten() {
		var e = window.elementor;
		return e && e.documents && e.documents.documents ? e.documents.documents : {};
	}

	function kitId() {
		return window.elementor && window.elementor.config ? parseInt(window.elementor.config.kit_id, 10) || 0 : 0;
	}

	// Een wachtende waarde voor de kit pas overnemen als het nieuwe item in
	// het geheugen staat. Is de kit intussen opnieuw geladen, dan vervalt hij.
	function beoordeelWachtend() {
		if (!wachtend) return;
		var kit = documenten()[wachtend.kit];
		if (!kit || !kit.config || kit.config.mm_vingerafdruk !== wachtend.basis) {
			wachtend = null;
			return;
		}
		try {
			var lijst = kit.container && kit.container.settings && kit.container.settings.get(wachtend.lijst);
			if (lijst && typeof lijst.findWhere === 'function' && lijst.findWhere({ _id: wachtend.item })) {
				kit.config.mm_vingerafdruk = wachtend.hash;
				wachtend = null;
			}
		} catch (e) {}
	}

	function bekend() {
		beoordeelWachtend();
		var docs = documenten(), delen = [];
		Object.keys(docs).forEach(function (id) {
			var c = docs[id] && docs[id].config;
			if (c && /^[a-f0-9]{32}$/.test(c.mm_vingerafdruk || '')) delen.push(parseInt(id, 10) + ':' + c.mm_vingerafdruk);
		});
		return delen.join(',');
	}

	// globaal: {lijst, item} bij het aanmaken van een globale kleur of
	// typografie via REST, anders leeg.
	function bijwerken(waarde, globaal) {
		if (!waarde) return;
		var docs = documenten(), kit = kitId();
		waarde.split(',').forEach(function (deel) {
			var m = /^\s*(\d+):([a-f0-9]{32})\s*$/.exec(deel);
			if (!m || !docs[m[1]] || !docs[m[1]].config) return;
			if (parseInt(m[1], 10) !== kit) {
				docs[m[1]].config.mm_vingerafdruk = m[2];
			} else if (globaal) {
				wachtend = { kit: kit, hash: m[2], basis: docs[m[1]].config.mm_vingerafdruk, lijst: globaal.lijst, item: globaal.item };
			}
		});
	}

	function globaalItem(url, methode) {
		if (String(methode || 'GET').toUpperCase() !== 'POST') return null;
		var m = /elementor\/v1\/globals\/(colors|typography)\/([\w-]+)/.exec(decodeURIComponent(url.pathname + url.search));
		return m ? { lijst: 'custom_' + m[1], item: m[2] } : null;
	}

	function toonDialoog() {
		try {
			if (!dialoog) {
				dialoog = window.elementorCommon.dialogsManager.createWidget('alert', {
					id: 'mm-elementor-versie',
					headerMessage: 'Niet opgeslagen',
					message: TEKST,
					strings: { confirm: 'OK' }
				});
			}
			dialoog.show();
			return true;
		} catch (e) {
			return false;
		}
	}

	// Alleen als de dialoog er staat; anders blijft de toast de melding.
	function zonderToast() {
		var n = window.elementor && window.elementor.notifications;
		if (!n || n.mmZonderVersieToast || typeof n.showToast !== 'function') return;
		var origineel = n.showToast;
		n.mmZonderVersieToast = true;
		n.showToast = function (opties) {
			if (opties && opties.message === TEKST) return;
			return origineel.apply(this, arguments);
		};
	}

	function geweigerd(waarde) {
		var m = waarde && /^(\d+):([a-f0-9]{32}):([a-f0-9]{32}):(auto|opslaan)$/.exec(waarde.trim());
		if (!m) return;
		if (window.console) console.warn('[Mediamora Toolkit] Opslaan geweigerd voor document ' + m[1] + ' (' + (m[4] === 'auto' ? 'autosave' : 'opslaan') + '): vingerafdruk bij laden ' + m[2] + ', in de database ' + m[3] + '.');
		if (m[4] === 'auto' && gemeld[m[1]]) return;
		gemeld[m[1]] = true;
		if (toonDialoog()) zonderToast();
	}

	$.ajaxPrefilter(function (opties, origineel, xhr) {
		if (opties.crossDomain) return;
		var waarde = bekend();
		if (waarde) xhr.setRequestHeader(KOP, waarde);
		xhr.always(function () {
			try {
				bijwerken(xhr.getResponseHeader(KOP));
				geweigerd(xhr.getResponseHeader(GEWEIGERD));
			} catch (e) {}
		});
	});

	if (typeof window.fetch === 'function') {
		var fetchOrigineel = window.fetch;
		window.fetch = function (invoer, init) {
			var waarde = '', globaal = null;
			try {
				var url = new URL(invoer instanceof Request ? invoer.url : String(invoer), location.href);
				if (url.origin === location.origin) {
					waarde = bekend();
					globaal = globaalItem(url, (init && init.method) || (invoer instanceof Request ? invoer.method : 'GET'));
				}
			} catch (e) {}
			if (!waarde) return fetchOrigineel.apply(window, arguments);
			init = Object.assign({}, init);
			var koppen = new Headers(init.headers || (invoer instanceof Request ? invoer.headers : undefined));
			koppen.set(KOP, waarde);
			init.headers = koppen;
			return fetchOrigineel.call(window, invoer, init).then(function (antwoord) {
				try { bijwerken(antwoord.headers.get(KOP), globaal); } catch (e) {}
				return antwoord;
			});
		};
	}
})(window.jQuery);
JS;
}
