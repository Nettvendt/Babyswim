<?php
/**
 * Plugin Name:				Babyswim - Påmelding til kurs for WooCommerce
 * Description:				📆 Av Nettvendt. Gjør produkter til kurs man kan melde seg på til med deltakeres navn og fødselsdato. Kan vise en kursoversikt (tabell) med enten sidemal, kortkode eller blokk. Planlegging av kommende kursplan. Utskrift av deltakerliste.
 * Plugin URI:				https://nettvendt.no/
 * Version:					1.4
 * Author:					Knut Sparhell
 * Author URI:				https://profiles.wordpress.org/knutsp/
 * Requires at least:		5.2.1
 * WC requires at least:	4.0
 * WC tested up to:			4.2.0
 * Tested up to:			5.4.1
 * Text Domain:				webfacing-events
 *
 * @author knutsp
 */

//namespace Babyswim\knutsp;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WEBFACING_EVENTS = __FILE__;

setlocale( LC_ALL, 'nb_NO.UTF-8' );

require_once 'functions.php';
require_once 'class-kursoversikt.php';
require_once 'class-shortcode.php';
require_once 'class-kursoversikt-deltakere.php';
require_once 'class-kursoversikt-settings.php';
require_once 'class-kursoversikt-preorder.php';
require_once 'class-kursoversikt-meta.php';
require_once 'class-pagetemplater.php';

if ( ! function_exists( 'get_plugin_data' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

new Kursoversikt;
new Kursoversikt_Shortcode;
new Kursoversikt_Settings;

if ( is_admin() ) {
	new Kursoversikt_Deltakere;
	register_activation_hook( __FILE__, [ 'Kursoversikt', 'install' ] );
}