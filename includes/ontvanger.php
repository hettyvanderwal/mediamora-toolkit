<?php
/**
 * Mediamora Toolkit, het adres van Mediamora voor meldingen.
 *
 * Gebruikt door de Formuliermonitor en door Afsluiten op einddatum. Per site
 * te overschrijven in wp-config.php met define( 'MM_MONITOR_ONTVANGER', ... ).
 *
 * Stond tot en met 1.24.0 in de module Formuliermonitor, en bestond dus niet
 * als die module niet laadde. De losse Formuliermonitor definieert hem op
 * dezelfde manier en laadt eerder; dan geldt die.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MM_MONITOR_ONTVANGER' ) ) {
	define( 'MM_MONITOR_ONTVANGER', 'hetty@mediamora.nl' );
}
