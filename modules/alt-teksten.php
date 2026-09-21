<?php
/**
 * Mediamora Toolkit, module: Alt-teksten
 * Overgenomen uit mediamora-alt-teksten.php 1.1 (kop zei nog 1.0). Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mediamora_Alt_Teksten {

	const BRON  = '_mm_alt_bron';
	const INDEX = '_mm_alt_index';
	const DATUM = '_mm_alt_datum';

	private static $bezig = false;

	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'bij_upload' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'filter_attributes' ), 20, 2 );
		add_filter( 'elementor/image_size/get_attachment_image_html', array( __CLASS__, 'filter_elementor' ), 20, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'handmatig' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'handmatig' ), 10, 3 );
		add_filter( 'manage_media_columns', array( __CLASS__, 'kolom' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'kolom_inhoud' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter_dropdown' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
	}

	private static function bedrijfsnaam() {
		$naam = defined( 'MM_ALT_BEDRIJFSNAAM' ) ? MM_ALT_BEDRIJFSNAAM : get_bloginfo( 'name' );
		return trim( (string) apply_filters( 'mm_alt_bedrijfsnaam', $naam ) );
	}

	private static function is_afbeelding( $id ) {
		$mime = get_post_mime_type( $id );
		if ( ! $mime ) {
			return false;
		}
		return strpos( $mime, 'image/' ) === 0 && $mime !== 'image/svg+xml';
	}

	private static function nietszeggend( $tekst ) {
		$t = trim( (string) $tekst );
		if ( $t === '' || mb_strlen( $t ) < 4 ) {
			return true;
		}
		$patronen = array(
			'/^img[\s_-]*\d+/i',
			'/^dscn?[\s_-]*\d+/i',
			'/^pxl[\s_-]*\d+/i',
			'/^gopr[\s_-]*\d+/i',
			'/^p\d{7,}/i',
			'/^(whats\s?app|schermafbeelding|scherm\s?opname|screenshot|screen\s?shot)/i',
			'/^(foto|photo|image|afbeelding|plaatje|bestand|file|untitled|unnamed|naamloos|download|kopie|copy)[\s_-]*\d*$/i',
			'/^[\d\s_.,-]+$/',
		);
		foreach ( $patronen as $p ) {
			if ( preg_match( $p, $t ) ) {
				return true;
			}
		}
		return false;
	}

	private static function uit_bestandsnaam( $id ) {
		$file = (string) get_post_meta( $id, '_wp_attached_file', true );
		if ( $file === '' ) {
			$file = (string) get_post_field( 'guid', $id );
		}
		if ( $file === '' ) {
			return '';
		}
		$naam = pathinfo( basename( $file ), PATHINFO_FILENAME );
		$naam = preg_replace( '/-(scaled|rotated)(-\d+)?$/i', '', $naam );
		$naam = preg_replace( '/-\d+x\d+$/', '', $naam );
		$naam = str_replace( array( '_', '-', '+' ), ' ', $naam );
		$naam = preg_replace( '/\s+/', ' ', $naam );
		$naam = trim( $naam );
		return $naam === '' ? '' : ucfirst( $naam );
	}

	private static function stel_samen( $paginatitel, $index ) {
		$paginatitel = trim( (string) $paginatitel );
		$bedrijf     = self::bedrijfsnaam();
		$delen       = array();
		if ( $paginatitel !== '' ) {
			$delen[] = $paginatitel;
		}
		if ( $bedrijf !== '' && strcasecmp( $bedrijf, $paginatitel ) !== 0 ) {
			$delen[] = $bedrijf;
		}
		$alt = trim( html_entity_decode( implode( ' ', $delen ), ENT_QUOTES, 'UTF-8' ) );
		if ( $alt === '' ) {
			$alt = 'Afbeelding';
		}
		if ( (int) $index > 1 ) {
			$alt .= ' ' . (int) $index;
		}
		return $alt;
	}

	private static function volgend_nummer( $parent ) {
		global $wpdb;
		$parent = (int) $parent;
		if ( $parent > 0 ) {
			$aantal = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = 'pagina' WHERE p.post_type = 'attachment' AND p.post_parent = %d",
				self::BRON,
				$parent
			) );
			return $aantal + 1;
		}
		$aantal = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = 'pagina' WHERE p.post_type = 'attachment' AND p.post_parent = 0 AND p.post_date_gmt > %s",
			self::BRON,
			gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
		) );
		return $aantal + 1;
	}

	public static function bij_upload( $id ) {
		if ( ! self::is_afbeelding( $id ) ) {
			return;
		}
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		if ( $alt !== '' && ! self::nietszeggend( $alt ) ) {
			return;
		}
		$uit_naam = self::uit_bestandsnaam( $id );
		if ( $uit_naam !== '' && ! self::nietszeggend( $uit_naam ) && count( preg_split( '/\s+/', $uit_naam ) ) >= 2 ) {
			self::zet( $id, $uit_naam, 'bestandsnaam', 1 );
			return;
		}
		$parent = (int) get_post_field( 'post_parent', $id );
		$titel  = $parent ? get_the_title( $parent ) : '';
		$index  = self::volgend_nummer( $parent );
		self::zet( $id, self::stel_samen( $titel, $index ), 'pagina', $index );
	}

	private static function zet( $id, $alt, $bron, $index ) {
		self::$bezig = true;
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		self::$bezig = false;
		update_post_meta( $id, self::BRON, $bron );
		update_post_meta( $id, self::INDEX, (int) $index );
		update_post_meta( $id, self::DATUM, current_time( 'mysql' ) );
	}

	public static function handmatig( $meta_id, $post_id, $meta_key ) {
		if ( self::$bezig ) {
			return;
		}
		if ( $meta_key !== '_wp_attachment_image_alt' ) {
			return;
		}
		delete_post_meta( $post_id, self::BRON );
		delete_post_meta( $post_id, self::INDEX );
		delete_post_meta( $post_id, self::DATUM );
	}

	private static function huidige_paginatitel() {
		if ( is_admin() ) {
			return '';
		}
		if ( empty( $GLOBALS['wp_query'] ) || ! ( $GLOBALS['wp_query'] instanceof WP_Query ) ) {
			return '';
		}
		if ( ! is_singular() ) {
			return '';
		}
		$id = get_queried_object_id();
		return $id ? (string) get_the_title( $id ) : '';
	}

	private static function alt_voor( $id ) {
		if ( get_post_meta( $id, self::BRON, true ) !== 'pagina' ) {
			return null;
		}
		$titel = self::huidige_paginatitel();
		if ( $titel === '' ) {
			return null;
		}
		return self::stel_samen( $titel, (int) get_post_meta( $id, self::INDEX, true ) );
	}

	public static function filter_attributes( $attr, $attachment ) {
		if ( empty( $attachment->ID ) ) {
			return $attr;
		}
		$nieuw = self::alt_voor( $attachment->ID );
		if ( $nieuw !== null ) {
			$attr['alt'] = $nieuw;
		}
		return $attr;
	}

	public static function filter_elementor( $html, $settings, $image_size_key, $image_key ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return $html;
		}
		if ( empty( $settings[ $image_key ]['id'] ) ) {
			return $html;
		}
		$nieuw = self::alt_voor( (int) $settings[ $image_key ]['id'] );
		if ( $nieuw === null ) {
			return $html;
		}
		$vervang = ' alt="' . esc_attr( $nieuw ) . '"';
		if ( preg_match( '/\salt="[^"]*"/', $html ) ) {
			return preg_replace_callback( '/\salt="[^"]*"/', function () use ( $vervang ) {
				return $vervang;
			}, $html, 1 );
		}
		return preg_replace_callback( '/<img\s/', function () use ( $vervang ) {
			return '<img' . $vervang . ' ';
		}, $html, 1 );
	}

	public static function kolom( $kolommen ) {
		$kolommen['mm_alt'] = 'Alt-tekst';
		return $kolommen;
	}

	public static function kolom_inhoud( $kolom, $post_id ) {
		if ( $kolom !== 'mm_alt' ) {
			return;
		}
		if ( ! self::is_afbeelding( $post_id ) ) {
			echo '-';
			return;
		}
		$alt  = trim( (string) get_post_meta( $post_id, '_wp_attachment_image_alt', true ) );
		$bron = get_post_meta( $post_id, self::BRON, true );
		if ( $alt === '' ) {
			echo '<em>leeg</em>';
			return;
		}
		echo esc_html( $alt );
		if ( $bron ) {
			$label = $bron === 'pagina' ? 'automatisch, volgt de pagina' : 'automatisch, uit bestandsnaam';
			echo '<br><span style="color:#996800;">' . esc_html( $label ) . '</span>';
		}
	}

	public static function filter_dropdown() {
		$scherm = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $scherm || $scherm->id !== 'upload' ) {
			return;
		}
		$huidig = isset( $_GET['mm_alt'] ) ? sanitize_key( wp_unslash( $_GET['mm_alt'] ) ) : '';
		echo '<select name="mm_alt">';
		echo '<option value="">Alle alt-teksten</option>';
		echo '<option value="auto"' . selected( $huidig, 'auto', false ) . '>Automatisch ingevuld</option>';
		echo '<option value="eigen"' . selected( $huidig, 'eigen', false ) . '>Zelf ingevuld of leeg</option>';
		echo '</select>';
	}

	public static function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'post_type' ) !== 'attachment' ) {
			return;
		}
		$keuze = isset( $_GET['mm_alt'] ) ? sanitize_key( wp_unslash( $_GET['mm_alt'] ) ) : '';
		if ( $keuze === 'auto' ) {
			$query->set( 'meta_query', array( array( 'key' => self::BRON, 'compare' => 'EXISTS' ) ) );
		} elseif ( $keuze === 'eigen' ) {
			$query->set( 'meta_query', array( array( 'key' => self::BRON, 'compare' => 'NOT EXISTS' ) ) );
		}
	}
}

Mediamora_Alt_Teksten::init();
