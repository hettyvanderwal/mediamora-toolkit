<?php
/**
 * Mediamora Toolkit, module: Schema op alle pagina's
 * Wordt alleen geladen als de module aanstaat.
 *
 * Staat in Rank Math het standaardschema voor pagina's op "Geen"
 * (pt_page_default_rich_snippet = off), dan laat Rank Math op pagina's zonder
 * eigen schema ook Organization, WebSite en WebPage weg. Alleen de homepage
 * krijgt ze dan nog. Zie can_add_global_entities() in
 * seo-by-rank-math/includes/modules/schema/class-jsonld.php.
 *
 * Deze module zet ze terug op pagina's en berichten, via het filter
 * rank_math/schema/add_global_entities. Het schematype zelf blijft van Rank
 * Math: er komt geen Article bij, want dat hangt niet van dit filter af.
 *
 * Op categorie-, tag- en taxonomiepagina's roept Rank Math hetzelfde filter
 * aan. Daar blijft de waarde ongewijzigd, zodat de instelling
 * remove_<taxonomy>_snippet_data van Rank Math blijft gelden.
 *
 * De toolkit laadt voor Rank Math, dus RANK_MATH_VERSION bestaat hier nog
 * niet. Daarom pas op plugins_loaded kijken of Rank Math er is.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'mm_schema_basis_registreren' );

function mm_schema_basis_registreren() {
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		add_filter( 'rank_math/schema/add_global_entities', 'mm_schema_basis_globaal' );
	}
}

/**
 * @param bool|string $toevoegen Wat Rank Math zelf zou doen.
 */
function mm_schema_basis_globaal( $toevoegen ) {
	return is_singular() ? true : $toevoegen;
}
