<?php

function get_covid_coupons( string $email ): array {	// 'ninajohannessen@live.no'

	$email = explode( '<', $email )[1];
	$email = explode( '>', $email )[0];
	$args = [
		'status'       => 'completed',
		'type'         => 'shop_order',
		'date_created' => '>=2020-02-23',
		'customer'     => $email,
	];
	$orders = wc_get_orders( $args );
	$coupons = [];
	foreach ( $orders as $order ) {
		foreach ( $order->get_items() as $order_item ) {
			$code = explode( ' ', $order_item->get_meta( 'coupon' ), 2 )[1];
			$coupons[ $code ] = get_page_by_title( $code, OBJECT, 'shop_coupon' )->post_excerpt;
		}
	}
	return $coupons;
}
