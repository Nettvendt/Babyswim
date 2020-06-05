<?php
/**
 * 
 */
require '../../../wp-load.php';

function swim_exportmailchimp() {
	$stamp = date_i18n( 'ymdHi' );
	header( 'Content-Type: text/csv' );
	header( 'Content-Disposition: attachment; filename=' . strtolower( get_bloginfo() ) . '-mailchimp-' . $stamp . '.csv' );
	echo 'Email Address,First Name,Last Name', PHP_EOL;
	foreach ( $_POST['mailchimp'] as $line ) {
		echo $line, PHP_EOL;
	}
}

swim_exportmailchimp();

//function swim_order() {
//	global $wpdb;
//	echo '<pre>';
//	$cols = $wpdb->get_col( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key LIKE '_billing_address_1'" );
//	foreach( $cols as $col ) {
//		$new = Kursoversikt_Meta::sanitize_billing_address_1( $col );
//		if ( $col != $new ) {
//			echo $col, PHP_EOL, $new, PHP_EOL;
//		}
//	}
//	echo '</pre>';
//}
//swim_order();