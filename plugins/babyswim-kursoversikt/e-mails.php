<?php
if ( ! class_exists( 'WP' ) ) {
	exit;
}

/**
 * Unhook and remove WooCommerce default emails.
 */
add_action( 'woocommerce_email', function( $emailer ) {
	if ( WP_DEBUG ) {
		/**
		 * Sending emails during store events
		 **/
		remove_action( 'woocommerce_low_stock_notification',            [ $emailer, 'low_stock' ] );
		remove_action( 'woocommerce_no_stock_notification',             [ $emailer, 'no_stock'  ] );
		remove_action( 'woocommerce_product_on_backorder_notification', [ $emailer, 'backorder' ] );
		
		// New order
		remove_action( 'woocommerce_order_status_pending_to_processing_notification',   [ $emailer->emails['WC_Email_New_Order'                ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_pending_to_completed_notification',    [ $emailer->emails['WC_Email_New_Order'                ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification',      [ $emailer->emails['WC_Email_New_Order'                ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_failed_to_processing_notification',    [ $emailer->emails['WC_Email_New_Order'                ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_failed_to_completed_notification',     [ $emailer->emails['WC_Email_New_Order'                ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_failed_to_on-hold_notification',       [ $emailer->emails['WC_Email_New_Order'                ], 'trigger' ] );
		
		// Processing order
		remove_action( 'woocommerce_order_status_pending_to_processing_notification',   [ $emailer->emails['WC_Email_Customer_Processing_Order'], 'trigger' ] );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification',      [ $emailer->emails['WC_Email_Customer_Processing_Order'], 'trigger' ] );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification',      [ $emailer->emails['WC_Email_Customer_On_Hold_Order'   ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_cancelled_notification',               [ $emailer->emails['WC_Email_Cancelled_Order'          ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_failed_notification',                  [ $emailer->emails['WC_Email_Failed_Order'             ], 'trigger' ] );
		
		// Completed order
		remove_action( 'woocommerce_order_status_completed_notification',               [ $emailer->emails['WC_Email_Customer_Completed_Order' ], 'trigger' ] );
			
		// Note
//		remove_action( 'woocommerce_new_customer_note_notification',                    [ $emailer->emails['WC_Email_Customer_Note'            ], 'trigger' ] );
	} else {
		// Processing order
		remove_action( 'woocommerce_order_status_completed_to_processing_notification', [ $emailer->emails['WC_Email_Customer_Processing_Order'], 'trigger' ] );
		remove_action( 'woocommerce_order_status_completed_to_pending_notification',    [ $emailer->emails['WC_Email_Customer_Processing_Order'], 'trigger' ] );
		remove_action( 'woocommerce_order_status_completed_to_pending_notification',    [ $emailer->emails['WC_Email_Customer_On_Hold_Order'   ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_completed_to_on-hold_notification',    [ $emailer->emails['WC_Email_Customer_Processing_Order'], 'trigger' ] );
		remove_action( 'woocommerce_order_status_completed_to_on-hold_notification',    [ $emailer->emails['WC_Email_Customer_On_Hold_Order'   ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_pending_to_on-hold_notification',      [ $emailer->emails['WC_Email_Customer_On_Hold_Order'   ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_pending_notification',                 [ $emailer->emails['WC_Email_Customer_On_Hold_Order'   ], 'trigger' ] );
		remove_action( 'woocommerce_order_status_on-hold_notification',                 [ $emailer->emails['WC_Email_Customer_On_Hold_Order'   ], 'trigger' ] );
	}
}, PHP_INT_MAX );