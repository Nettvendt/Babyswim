<?php
/**
 * 
 */
require '../../../wp-load.php';

function swim_exportmailchimp() {
	header( 'Content-Type: text/csv' );
	header( 'Content-Disposition: attachment; filename=babyswim-mailchimp.csv' );
	echo 'Email Address,First Name,Last Name', PHP_EOL;
	foreach ( $_POST['mailchimp'] as $line ) {
		echo $line, PHP_EOL;
	}
}

swim_exportmailchimp();