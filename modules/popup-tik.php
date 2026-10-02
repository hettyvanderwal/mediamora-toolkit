<?php
/**
 * Mediamora Toolkit, module: Mobiel menu, vroege tik
 * Overgenomen uit de testcode in functions.php (Schuurschatten, Falcon-i, sanbao.be).
 * Wordt alleen geladen als de module aanstaat.
 *
 * De hamburger opent een popup van Elementor Pro via een link als
 * #elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3D<base64>. De knop is
 * meteen zichtbaar, maar de popup werkt pas als Elementor Pro klaar is. Een tik
 * in die tussentijd ging verloren; Elementor opent hem daarna ook niet via de
 * hash. Dit script vangt zo'n tik op, onthoudt welke popup bedoeld was en
 * opent die zodra Elementor Pro zover is.
 *
 * Grijpt alleen in zolang Elementor Pro nog niet klaar is. Daarna gaat elke tik
 * gewoon naar Elementor.
 *
 * - data-no-defer en data-no-optimize zijn verplicht: met "JS uitgesteld laden"
 *   van LiteSpeed werd het script anders een uitgestelde data-URI en kwam het
 *   te laat.
 * - De herhaallus is nodig omdat de eerste aanroep van showPopup soms wordt
 *   genegeerd.
 * - Valt de tik pas na het load-event, dan start de lus meteen.
 * - Is Elementor Pro 15 seconden na de eerste opgevangen tik nog niet klaar,
 *   dan stopt het onderscheppen voor de rest van de paginaweergave, zodat de
 *   module nooit een dode knop veroorzaakt.
 * - window.mmPopupVroegeTik voorkomt dat het script twee keer actief wordt.
 *   Staat de testversie nog in functions.php (script-id mm-popup-vroege-tik,
 *   zonder die vlag), dan doet deze versie niets. Daarom staat dit script op
 *   prioriteit 2, na de testversie op 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hoort het script op deze pagina? Alleen op de voorkant, alleen met
 * Elementor Pro, niet in de editor of het voorbeeld van Elementor.
 */
function mm_popup_tik_nodig() {

	if ( is_admin() || ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
		return false;
	}

	if ( isset( $_GET['elementor-preview'] ) ) {
		return false;
	}

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
			return false;
		}
		if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
			return false;
		}
	}

	return true;
}

function mm_popup_tik_script() {

	if ( ! mm_popup_tik_nodig() ) {
		return;
	}
	?>
<script id="mm-toolkit-popup-tik" data-no-defer="1" data-no-optimize="1">
(function(){
	if (window.mmPopupVroegeTik || document.getElementById('mm-popup-vroege-tik')) return;
	window.mmPopupVroegeTik = true;
	var wacht = null, pogingen = 0, loopt = false, klep = null, opgegeven = false;
	function klaar(){
		var pf = window.elementorProFrontend, ef = window.elementorFrontend;
		return !!(pf && pf.modules && pf.modules.popup && ef && ef.documentsManager && ef.documentsManager.documents);
	}
	function zichtbaar(id){
		var m = document.getElementById('elementor-popup-modal-' + id);
		return !!(m && window.getComputedStyle(m).display !== 'none');
	}
	function open(){
		if (!wacht) { loopt = false; return; }
		loopt = true;
		if (zichtbaar(wacht.id)) { wacht = null; loopt = false; return; }
		if (klaar() && window.elementorFrontend.documentsManager.documents[wacht.id]) {
			try { window.elementorProFrontend.modules.popup.showPopup(wacht); } catch (err) {}
		}
		if (++pogingen < 40) { setTimeout(open, 250); } else { wacht = null; loopt = false; }
	}
	document.addEventListener('click', function(e){
		if (opgegeven) return;
		var a = e.target && e.target.closest ? e.target.closest('a[href*="popup%3Aopen"],a[href*="popup:open"]') : null;
		if (!a || klaar()) return;
		e.preventDefault();
		try {
			var m = decodeURIComponent(a.getAttribute('href')).match(/settings=([^&]+)/);
			wacht = JSON.parse(atob(m[1]));
			pogingen = 0;
		} catch (err) { wacht = null; }
		if (!klep) {
			klep = setTimeout(function(){
				if (!klaar()) { opgegeven = true; wacht = null; }
			}, 15000);
		}
		if (wacht && !loopt && document.readyState === 'complete') { loopt = true; setTimeout(open, 0); }
	}, true);
	window.addEventListener('load', function(){ setTimeout(function(){ if (!loopt) open(); }, 0); });
})();
</script>
	<?php
}

add_action( 'wp_head', 'mm_popup_tik_script', 2 );
