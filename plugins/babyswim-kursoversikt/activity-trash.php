<?php

add_action( 'admin_init', function() {
	$number = current_user_can( 'delete_published_activities' ) ? 100 : 10;
	$activities = get_posts( [
		'post_type'      => 'activity',
		'post_status'    => 'trash',
		'date_query'     => [ 'before' => date( 'Y-m-d H:i:s', strtotime( '-16 days' ) ) ],
		'orderby'        => 'post_date',
		'order'          => 'DESC',
		'posts_per_page' => $number,
	] );

	if ( is_array( $activities ) ) {
		foreach( $activities as $activity ) {
			wp_delete_post( $activity->ID );
		}
	}

	$activities = get_posts( [
		'post_type'      => 'activity',
		'date_query'     => [ 'before' => date( 'Y-m-d H:i:s', strtotime( '-9 days' ) ) ],
		'orderby'        => 'post_date',
		'order'          => 'DESC',
		'posts_per_page' => $number,
	] );

	if ( is_array( $activities ) ) {
		foreach( $activities as $activity ) {
			wp_trash_post( $activity->ID );
		}
	}

	$activities = get_posts( [
		'post_type'      => 'activity',
		'date_query'     => [ 'before' => date( 'Y-m-d H:i:s', strtotime( '-16 hours' ) ) ],
		'post_status'    => 'trash',
		'meta_query'     => [ 'relation' => 'OR',
			[ 'key' => 'wp_user_activity_object_type', 'value' => 'comment' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_Single_Product' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_Single_Order' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_User_Submit' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_Cart_Update' ],
		],
		'orderby'        => 'post_date',
		'order'          => 'DESC',
		'posts_per_page' => $number,
	] );

	if ( is_array( $activities ) ) {
		foreach( $activities as $activity ) {
			wp_delete_post( $activity->ID );
		}
	}
	
	$activities = get_posts( [
		'post_type'      => 'activity',
		'date_query'     => [ 'before' => date( 'Y-m-d H:i:s', strtotime( '-9 hours' ) ) ],
		'meta_query'     => [ 'relation' => 'OR',
			[ 'key' => 'wp_user_activity_object_type', 'value' => 'comment' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_Single_Product' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_Single_Order' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_User_Submit' ],
			[ 'key' => 'wp_user_activity_object_name', 'value' => 'MailChimp_WooCommerce_Cart_Update' ],
			],
		'orderby'        => 'post_date',
		'order'          => 'DESC',
		'posts_per_page' => $number,
	] );

	if ( is_array( $activities ) ) {
		foreach( $activities as $activity ) {
			wp_trash_post( $activity->ID );
		}
	}

	if ( class_exists( 'EmailLog\Core\DB\TableManager' ) ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailLog\Core\DB\TableManager::LOG_TABLE_NAME;
		$before = date( 'Y-m-d H:i:s', strtotime( '-23 days' ) );
		$wpdb->query( "DELETE FROM $table_name WHERE `sent_date` < '$before' ORDER BY `sent_date` DESC LIMIT $number" );
	}
}, 9, 0 );