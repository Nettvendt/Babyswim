<?php

add_filter( 'block_lab_template_path', function( $template_path ) {
	$template_path = untrailingslashit( __DIR__ );
	return $template_path;	
} );

add_filter( 'x-woocommerce_my_account_my_orders_actions', function( $actions, $order ) {
	$not_expired = current_time( 'U' ) < Kursoversikt::$refund_req_to || current_user_can( 'edit_shop_orders' ) || $order->get_status() == 'processing';
	if ( $not_expired && in_array( $order->get_status(), [ 'completed', 'processing' ] ) && $order->is_paid() && $order->get_date_created()->getTimestamp() >=  Kursoversikt::$refund_c_from ) {
		$actions['view'] = [
			'url'  => add_query_arg( [ 'order_id' => $order->get_id() ], get_the_permalink( get_page_by_path( 'refusjon' ) ) ),
			'name' => 'Refusjon?',
		];
	}
	return $actions;
}, 10, 2 );

add_filter( 'manage_edit-shop_order_columns', function( $columns ) {
	$order_status = $columns['order_status'];
	$order_total  = $columns['order_total' ];
	$wc_actions   = $columns['wc_actions'  ];
	if ( class_exists( 'WooCommerceArchiveOrders' ) && $_GET['post_status'] !== 'trash' && empty( $_GET['archived'] ) ) {
		$columns['order_date'] = $columns['order_date'] . '/arkiveres';
	}
	unset ( $columns['order_status'], $columns['order_total'], $columns['wc_actions'] );
	$columns[ Kursoversikt::$woo_event_tax ] = 'Kurs';
	$columns['participants'] = 'Deltaker(e)';
	if ( isset( $_GET['s'] ) || ( isset( $_GET['post_status'] ) && ( WP_DEBUG || in_array( $_GET['post_status'], [ 'wc-processing', /*'wc-on-hold', 'wc-cancelled', */'wc-refunded', 'wc-completed' ] ) ) ) ) {
		$columns['refund'] = 'Refusjon';
	}
	$columns['order_status'] = $order_status;
	$columns['order_total' ] = $order_total;
//	if ( in_array( ( $_GET['post_status'] ?? 'all' ), [ 'all', 'wc-on-hold', 'wc-processing' ] ) && ( $_GET['archived'] ?? 'no' ) !== 'yes' ) {
	if ( ( $_GET['post_status'] ?? 'all' ) !== 'trash' && ( $_GET['archived'] ?? 'no' ) !== 'yes' ) {
		$columns['wc_actions' ] = $wc_actions;
	}
	return $columns;
} );

add_action( 'manage_shop_order_posts_custom_column', function( $column, $order_id ) {
	$order = wc_get_order( $order_id );
	if ( $column === Kursoversikt::$woo_event_tax ) {
		$cats = [];
		foreach ( $order->get_items() as $order_item ) {
			$event_id = $order_item->get_product_id();
			$cats[] = implode( '+<br />', array_map( function( $cat ) use( $event_id ) {
				$loc = wp_list_pluck( get_the_terms( $event_id, Kursoversikt::$woo_loc_tax ), 'name' )[0];
				$date = date_i18n( 'l j. F Y H:i', Kursoversikt::get_event_start_time( $event_id ) );
				$cat = '<span title="' . $loc . ', ' . $date . '.">' . $cat . '</span>';
				return $cat;
			}, wp_list_pluck( get_the_terms( $event_id, Kursoversikt::$woo_event_tax ), 'name' ) ) );
		}
		echo implode( ',<br />', $cats );
	} elseif ( class_exists( 'WooCommerceArchiveOrders' ) && $_GET['post_status'] !== 'trash' && empty( $_GET['archived'] ) && $column === 'order_date' ) {
		$archive  = intval( get_option( 'woocommerce_archive_orders_older_than_days' ) );
		if ( $archive ) {
			$datetime = $order->get_date_created();
			date_add( $datetime, date_interval_create_from_date_string( $archive . ' days' ) );
			$diff = date_diff( date_create(), $datetime )->format( '%r%a dager og %r%h timer' );
			echo '<br/><span title="Arkiveres automatisk ', $datetime->date_i18n( 'l j. F k\l G' ), ', om ', $diff, '.">', 'om ca ' . human_time_diff( $datetime->getTimestamp() ), '</span>';
		}
	} elseif ( $column === 'participants' ) {
		$meta = [];
		foreach ( $order->get_items() as $order_item_id => $order_item ) {
			$meta_data = $order_item->get_meta( 'participant', false );
			$participants = [];
			foreach ( $meta_data as $meta_item ) {
				$data = $meta_item->get_data();
				if ( $data['key'] == 'participant' ) {
					$in_age = Kursoversikt_Deltakere::in_age( $data['value'], $order_item );
					$age_diff = Kursoversikt_Deltakere::age_diff( $data['value'], $order_item );
					$datas = explode( ' ', trim( $data['value'] ), 2 );
					$datas[0] = date_i18n( 'd.m.y', strtotime( $datas[0] ) );
					$data = $datas[0] . ' ' . $datas[1];
					$participants[] = ( $order->get_status() == 'completed' ? Kursoversikt_Deltakere::get_participant_link( $data, $order_item ) : $data ) . (  in_array( $order->get_status(), [ 'cancelled', 'failed', 'trash' ] ) ? '' : ( $in_age ? '' : ' <strong style="color: red;">(' . $age_diff . ')</strong>' ) );
				}
			}
			if ( count( $participants ) ) {
				$meta[] = implode( ' +<br />', $participants );
			}
		}
		echo implode( ',<br />', $meta );
	} elseif ( $column == 'refund' ) {
		$meta = [];
		foreach ( $order->get_items() as $order_item ) {
			$data = wp_list_pluck( array_merge( $order_item->get_meta( 'refund', false ), $order_item->get_meta( 'coupon', false ) ), 'value' );
			$meta[] = mb_substr( $data[ array_key_last( $data ) ], 0, 29 );
		}
		echo implode( ',<br />', $meta );
//		echo $meta[ array_key_last( $meta ) ];
	}
}, 11, 2 );

add_filter( 'woocommerce_admin_order_actions', function( $actions, $order ) {
	$status = $order->get_status();
    if ( ! str_ends_with( $status, '-a' ) ) {
        $order_id = $order->get_id();
		$new_status = $status . '-a';
        $actions['archive'] = [
			'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=' . $new_status . '&order_id=' . $order_id ), 'woocommerce-mark-order-status' ),
			'name'      => 'Arkivér',
			'action'    => 'archive',
        ];
    }
    return $actions;
}, 11, 2 );

add_action( 'x-wp_ajax_archive_order', function ( $order_id ) {
	$order = wc_get_order( $order_id ?: intval( $_GET['order_id'] ) );
	$status = $order->get_status();
	if ( ! str_ends_with( $status, '-a' ) ) {
		$order->update_status( $status . '-a', '', false );
	}
	wp_die();
} );