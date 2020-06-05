<?php
echo PHP_EOL, '<div>';
$upd = 0;
$args = [
	'type'        => 'shop_order',
	'limit'       => -1,
	'orderby'     => 'id',
	'order'       => 'DESC',
//	'customer'    => 'therese.lodrup@samtalen.no'
//	'customer_id' => 791,
];
$orders = wc_get_orders( $args );
foreach ( $orders as $order ) {
	$order_items = $order->get_items();
	foreach ( $order_items as $order_item_id => $order_item ) {
		$item_quant = $order_item->get_quantity();
		$item_meta_key = 'participant';
		$order_item->delete_meta_data( $item_meta_key );
//		error_log( 'Delete ' . $order->get_id() . ' ' . $item_meta_key );
		$remove_item_meta_keys[0] = 'Deltaker';
		$remove_item_meta_keys[1] = 'Tvilling 1';
		$remove_item_meta_keys[2] = 'Tvilling 2';
		$remove_item_meta_keys[3] = 'Tvilling 3';
		foreach ( $remove_item_meta_keys as $remove_item_meta_key ) {
			$remove_meta_exists = $order_item->meta_exists( $remove_item_meta_key );
			if ( $remove_meta_exists ) {
				$order_item->delete_meta_data( $remove_item_meta_key );
				error_log( 'Delete ' . $order->get_id() . ' ' . $remove_item_meta_key );
			}
		}
		$product_id = $order_item->get_product_id();
		$order_id = $order->get_id();
		if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) {
			$item_quant += $order->get_qty_refunded_for_item( $order_item_id );
			if ( $order->get_qty_refunded_for_item( $order_item_id ) ) {
				error_log( 'Refund ' . $order->get_id() . ' ' . $order->get_qty_refunded_for_item( $order_item_id ) . ' ' . $item_quant );
			}
		}
		for ( $piece = 1; $piece <= $item_quant; $piece++ ) {
			$order_meta_key1 = $product_id . '_navn_' . $piece;
			$order_meta_key2 = $product_id . '_fodt_' . $piece;
			$orig_date = str_replace( ' ', '', trim( get_post_meta( $order_id, $order_meta_key2, true ) ) );
			$time = strtotime( $orig_date );
			$order_meta = ( $time && current_time( 'U' ) - $time > DAY_IN_SECONDS ? get_post_meta( $order_id, $order_meta_key2, true ) : '<code>' . trim( $orig_date ) . '</code>' ) . ' ' . get_post_meta( $order_id, $order_meta_key1, true );
			$order_item->add_meta_data( $item_meta_key, $order_meta, false );
			error_log( 'Add    ' . $order->get_id() . ' ' . $item_meta_key . ' ' . $order_meta );
			$upd++;
		}
		$order_item->save_meta_data();
	}
}
echo $args['limit'], ' ', $upd, ' ', $order_id;
echo PHP_EOL, '</div>';