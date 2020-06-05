<?php
/**
 * Don't call this file directly.
 */
if ( ! class_exists( 'WP' ) ) {
	die();
}

add_filter( 'core_sitemaps_taxonomies', function( $taxonomies ) {
	unset ( $taxonomies[ Kursoversikt::$pf . 'location'] );
	return $taxonomies;
} );