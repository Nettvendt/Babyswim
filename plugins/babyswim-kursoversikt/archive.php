<?php

add_action( 'plugins_loaded', function() {
	
	if ( defined( 'ARCHIVED_POST_STATUS_PLUGIN' ) ) {
		
		add_filter( 'bulk_actions-edit-product', function( $bulk_actions ) {
			$bulk_actions['archive'] = __( 'Arkiver', 'archived-post-status' );
			return $bulk_actions;
		} );

		add_filter( 'handle_bulk_actions-edit-product', function( $redirect_to, $action, $post_ids ) {
			if ( $action === 'archive' ) {
				foreach ( $post_ids as $post_id ) {
					wp_update_post( [ 'ID' => $post_id, 'post_status' => 'archive' ] );
				}
				$redirect_to = add_query_arg( [ 'archived' => count( $post_ids ) ], $redirect_to );
			}
			return $redirect_to;
		}, 10, 3 );

		add_action( 'admin_notices', function() {
			$count = intval( $_GET['archived'] ?: false );
			if ( $count ) {
				printf( '<div id="message" class="updated fade">' .
					_n( 'Arkiverte %d produkt.', 'Arkiverte %d produkter.', $count, 'archived-post-status' ) . '</div>',
					$count
				);
			}
		} );
		
		add_filter( 'aps_status_arg_show_in_admin_all_list', '__return_false' );
	}

	if ( class_exists( 'WooCommerceArchiveOrders' ) ) {

		// Adding to admin order list bulk dropdown a custom action 'custom_downloads'
		add_filter( 'bulk_actions-edit-shop_order', function( $actions ) {
			$actions['archive'] = __( 'Endre status til arkivert', 'woo-archive-orders' );
			return $actions;
		}, 20, 1 );

		// Make the action from selected orders
		add_filter( 'handle_bulk_actions-edit-shop_order', function( $redirect_to, $action, $post_ids ) {
			if ( $action === 'archive' ) {
				$processed_ids = [];

				foreach ( $post_ids as $post_id ) {
					$order = wc_get_order( $post_id );
					$status = $order->get_status();
					if ( ! str_ends_with( $status, '-a' ) ) {
						$order->update_status( $status . '-a', '', false );
					}
					$processed_ids[] = $post_id;
				}
			}
			$redirect_to = add_query_arg( [ 'archived' => count( $processed_ids ), 'processed_ids' => implode( ',', $processed_ids ) ], $redirect_to );
			return $redirect_to;
		}, 10, 3 );
		

		// The results notice from bulk action on orders
		add_action( 'admin_notices', function() {
			$count = intval( $_GET['archived'] ?: false );
			if ( $count ) {
				printf(
					'<div id="message" class="updated fade"><p>' .
						_n( 'Arkiverte %s ordre.', 'Arkivert %s ordrer.', $count, 'woo-archive-orders' ) . 
						'</p></div>',
					$count
				);
			}
		} );
		
	}
} );
