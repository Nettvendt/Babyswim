<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// todo: 'woocommerce_payment_complete_order_status'

add_action( 'woocommerce_before_add_to_cart_button', function() {
	echo PHP_EOL, '<div><p>';
//	echo PHP_EOL, ' <label for="fodt">Deltaker(e) født</label>';
//	echo PHP_EOL, ' <input type="date" id="fodt" name="fodt" min="', date_i18n( 'Y-m-d', strtotime( '-5 years' ) ),'" max="', date_i18n( 'Y-m-d', strtotime( '-1 month' ) ),'" />';
	woocommerce_form_field( 'fodt', [
		'type'        => 'date',
		'class'       => [ 'form-row-last' ],
		'label'       => '<span title="Kan endres i neste trinn.">Deltaker(e) født</span>',
		'custom_attributes' => [
			'min'     => date_i18n( 'Y-m-d', strtotime( '-5 years' ) ),
			'max'     => date_i18n( 'Y-m-d', strtotime( '-1 month' ) ),
		],
		'required'    => true,
	], '' );
	echo PHP_EOL, '</p></div>';
} );

add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data ) {
	if ( isset( $_POST['fodt'] ) ) {
		$cart_item_data['fodt'] = esc_attr( $_POST['fodt'] );
	}
	return $cart_item_data;
} );

/**
 * Add new fields based on quantity
 */ 
add_action( 'woocommerce_after_order_notes', 'babyswim_checkout_fields', 20, 1 );
function babyswim_checkout_fields( $checkout ) {
	$name = $checkout->get_posted_address_data( 'billing_first_name' ) . ' ' . $checkout->get_posted_address_data( 'billing_last_name' );
	$name = empty( trim( $name ) ) ? $checkout->get_posted_address_data( 'first_name', 'billing' ) . ' ' . $checkout->get_posted_address_data( 'last_name', 'billing' ) : $name;
	$name = empty( trim( $name ) ) ? $checkout->get_value( 'billing_first_name' ) . ' ' . $checkout->get_value( 'billing_last_name' ) : $name;
	$name = empty( trim( $name ) ) ? 'du' : $name;
    echo PHP_EOL, '<div id="babyswim_checkout_fields"><h2>Informasjon om barnet/barna ', $name, ' er forelder/foresatt til</h2>';
	echo PHP_EOL, '<p><small>Skal du melde på andre barn enn dine egne, så må du gjøre dette i en egen separat påmelding.</small></p>';

	$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
	$field_base_names = array_keys( $fields );

	// First Loop go through cart items
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        // 2nd Loop go through each unit related to item quantity
		$quantity = $cart_item['quantity'];
        for( $child_id = 1; $child_id <= $quantity; $child_id++ ) {

			$item_id = 0; //array_key_first( $field_base_names );	// First field
			$field_name = $cart_item['product_id'] . '_' . $field_base_names[ $item_id ] . '_' . $child_id;
			$product_name  = $cart_item['data']->get_name();
			$name_parts    = explode( ' / ', $product_name );
			$parts_count   = count( $name_parts );
			$product_name  = $name_parts[0];
			$product_name .= $parts_count > 1 ? '&nbsp;' . rtrim( substr( $name_parts[1], 0, 7 ) ) . ' ' . substr( $name_parts[1], -14, 8 ) : '';
			$product_name .= $parts_count > 3 ? '&nbsp;' . rtrim( substr( $name_parts[3], 0, 9 ) ) : '';
            woocommerce_form_field( $field_name, [
				'type'        => 'text',
				'class'       => [ $field_base_names[ $item_id ], 'form-row-first' ],
//				'label'       => 'Hva heter' . ( $quantity > 1 ? ' det ' . $child_id . '.' : '' ) . ' barnet på &laquo;' . $product_name . '&raquo;?',
//				'label'       => 'Hva er fornavnet til' . ( $quantity > 1 ? ' det ' . $child_id . '.' : '' ) . ' barnet på &laquo;' . $product_name . '&raquo;?',
				'label'       => 'Hva er <u>fornavnet</u> til' . ( $quantity > 1 ? ' den ' . $child_id . '. tvillingen' : ' barnet' ) . ' på &laquo;' . $product_name . '&raquo;?',
				'placeholder' => $fields[ $field_base_names[ $item_id ] ] . ' (kun)',
				'required'    => true,

            ], $checkout->get_value( $field_name ) );

			//$item_id = n (#2 to #n-1 ) Other fields not yet defined, for future
			
			$item_id = count( $field_base_names ) - 1; //array_key_last( $field_base_names );	// Last field
			$field_name = $cart_item['product_id'] . '_' . $field_base_names[ $item_id ] . '_' . $child_id;
//			$product_name = $cart_item['data']->get_name();	// Ubrukt her
			$value = $checkout->get_value( $field_name );
			$value = $value ? $value : $cart_item['fodt'];
			woocommerce_form_field( $field_name, [
				'type'        => 'date',
				'class'       => [ $field_base_names[ $item_id ], 'form-row-last' ],
                'label'       => '<br />Når er' . ( $quantity > 1 ? ' den ' . $child_id . '. tvillingen' : ' dette barnet' ) . ' født?',
//				'placeholder' => $fields[ $field_base_names[ $item_id ] ],
				'custom_attributes' => [
					'min'     => date_i18n( 'Y-m-d', strtotime( '-5 years' ) ),
					'max'     => date_i18n( 'Y-m-d', strtotime( '-1 month' ) ),
				],
				'required'    => true,
			], $value );
        }
    }
    echo '</div>';
}

/**
 * Update the order meta with all extra special checkout field values
 */
add_action( 'woocommerce_checkout_update_order_meta', 'babyswim_checkout_field_update_order_meta' );

function babyswim_checkout_field_update_order_meta( $order_id ) {
	$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
	$field_base_names = array_keys( $fields );
//	$ti_fields = [ 'navn' => 'first_name', 'fodt' => 'last_name' ]; // Transform to Tickera fields
	$order = wc_get_order( $order_id );
	$order_items = $order->get_items();
	foreach ( $order_items as $order_item_id => $order_item ) {
		$ticket_id = $order_item->get_product_id();
		$quantity  = $order_item->get_quantity();
		for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
			foreach ( $field_base_names as $field_base_name ) {
				$field_name = $ticket_id . '_' . $field_base_name . '_' . $child_id;
				if ( ! empty( $_POST[ $field_name ] ) ) {
					$parts = [];
					if ( $field_base_name == 'fodt' ) {
						$value = trim( esc_attr( $_POST[ $field_name ] ) );
						$value = is_numeric( $value ) ? substr( $value, 0, 2 ) . '.' . substr( $value, 2, 2 ) . '.' . substr( $value, 4 ) : $value;
						$value = trim( str_replace( '/', '.', $value ) );
						$value = strlen( $value ) < 9 ? trim( str_replace( '-', '.', $value ) ) : $value;
						$parts = explode( '.', $value );
						if ( count( $parts ) == 3 ) {
							foreach ( $parts as $key => $part ) {
								$parts[ $key ] = substr( '20' . intval( $part ), $key == 2 ? -4 : -2, $key == 2 ? 4 : 2 );
							}
							$value = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
						}
						$time  = strtotime( $value );
						$born  = $value;
					} else {
						$value = mb_convert_case( trim( esc_attr( $_POST[ $field_name ] ) ), MB_CASE_TITLE );
						$name  = $value;
					}
					update_post_meta( $order_id, $field_name, esc_attr( $value ) );
					error_log( 'Order ' . $order->get_id() . ': ' . var_export( $_POST[ $field_name ], true ) . ', ' . var_export( $value, true ) );
				}
			}
//			wc_add_order_item_meta( $order_item_id, $quantity == 1 ? 'Deltaker' : 'Tvilling ' . $child_id,  $born . ' ' . $name );
			wc_add_order_item_meta( $order_item_id, 'participant',  $born . ' ' . $name );
		}
//		$meta_query = [ [
//			'key'     => 'ticket_type_id',
//			'value'   => $ticket_id,
//			'compare' => 'NUMERIC',
//		] ];
//		$ticket_instances_query = new WP_Query( [
//			'post_type'      => 'tc_tickets_instances',
//			'post_parent'    => $order_id,
//			'meta_query'     => $meta_query,
//			'no_found_rows'  => true,
//			'posts_per_page' => $quantity,
//		] );
//		$ticket_instances = $ticket_instances_query->posts;
//		foreach ( $ticket_instances as $instance_id => $ticket_instance ) {
//			$child_id = $instance_id + 1;
//			foreach ( $fields as $field_base_name => $field_label ) {
//				$ti_field = $ti_fields[ $field_base_name ];
//				$field_name = $ticket_id . '_' . $field_base_name . '_' . $child_id;
//				if ( ! empty( $_POST[ $field_name ] ) ) {
//					update_post_meta( $ticket_instance->ID, $ti_field, esc_attr( $_POST[ $field_name ] ) );
//				}
//			}
//		}
    }
}

add_filter( 'woocommerce_order_item_display_meta_key', function( $key, $meta, $item ) {
	static $twins = [];
	if ( $key == 'participant' ) {
		$item_quant = $item->get_quantity();
		$item_id = $item->get_id();
		$twins[ $item_id ]++;
		$twin = $twins[ $item_id ];
		$key  = $item_quant > 1 ? 'Tvilling ' . $twin : 'Deltaker';
	} elseif ( $key == 'refund' ) {
		$key = 'Tilbakebetaling';
	} elseif ( $key == 'coupon' ) {
		$key = 'Rabattkupong';
	}
	return $key;
}, 10, 3 );

add_filter( 'woocommerce_order_item_display_meta_value', function( $value, $meta, $item ) {
	if ( $meta->__get( 'key' ) == 'participant' ) {
		$created = strtotime( $item->get_order()->get_date_created() );
		$value = explode( ' ', $value, 2 );
		$born = strtotime( strip_tags( $value[0] ) );
		$value[0] = $born ? date_i18n( 'd.m.y', $born ) : '(født mangler)';
		$value[0] = ! $born || $created - $born < MONTH_IN_SECONDS ? '<strong style="background-color: yellow;">' . $value[0] . '</strong>' : $value[0];
		$value = count( $value ) == 2 ? $value[0] . ' ' . $value[1] : $value[0];
	} elseif ( $meta->__get( 'key' ) == 'coupon' ) {
		$values = explode( ' ', $value, 2 );
		$value = $values[0] . ' <code>' . $values[1] . '</code>';
	}
	return $value;
}, 10, 3 );

add_action( 'xwoocommerce_review_order_after_order_total', function() {
	$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
	$field_base_names = array_keys( $fields );
    foreach( WC()->cart->get_cart() as $cart_item ) {
		$quantity = $cart_item['quantity'];
        for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
			$item_id = 0; //array_key_first( $field_base_names );	// First field
			$field_name = $cart_item['product_id'] . '_' . $field_base_names[ $item_id ] . '_' . $child_id;
//			var_dump( WC()->checkout()->get_value($field_name), $_POST[$field_name] );
		}
	}
} );

/**
 * Display the custom-field in orders view
 */
add_action( 'xwoocommerce_order_details_after_order_table', 'display_my_custom_field_in_order_details' );

function display_my_custom_field_in_order_details( $order ) {
	$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
//	$field_base_names = array_keys( $fields ); // Not needed
	$order_id = $order->get_id();
	$order_items = $order->get_items();

    ?>
	<section id="<?=Kursoversikt::$pf?>participants" class="woocommerce-order-details">
		<h2 class="woocommerce-order-details__title">Deltaker(e)</h2>
		<table class="woocommerce-table woocommerce-table--customer-details shop_table order_details">
			<tbody>
<?php
	foreach ( $order_items as $order_item ) {
		$ticket_id = $order_item->get_product_id();
		$product = wc_get_product( $ticket_id );
		$quantity = $order_item->get_quantity();
?>
				<tr>
					<th scope="col" colspan="<?=2*count($fields)?>" class="woocommerce-table__product-name product-name" style="text-transform: none; color: black;"><?=$product->get_title()?></th>
				</tr>
<?php
		for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
?>
				<tr>
<?php
			foreach ( $fields as $field_base_name => $label ) {
				$field_name = $ticket_id . '_' . $field_base_name . '_' . $child_id;
				$field_value = get_post_meta( $order_id, $field_name, true );
				$field_value = $field_base_name == 'fodt' ? date_i18n( 'd.m.y', strtotime( $field_value ) ) : $field_value;
?>
					<th scope="row" class="woocommerce-table__product-name product-name" style="text-transform: none;"><?=$label?>:</th>
					<td><?=$field_value?></td>
<?php
			}
?>
				</tr>
<?php
		}
	}
?>
			</tbody>
        </table>
	</section>
<?php
}

add_filter( 'woocommerce_new_customer_data', function( $new_customer_data ) {
	$bad_user_names = [ 'post', 'kontakt' , 'info' ];
	$billing_email = esc_attr( explode( '@', mb_strtolower( $_POST['billing_email'] ) )[0] );
	if ( str_contains( $new_customer_data['user_login'], ' ' ) && ! in_array( $billing_email, $bad_user_names ) ) {
		$new_customer_data['user_login'] = str_replace( ' ', '.', sanitize_user( $billing_email ) );
	}
	return $new_customer_data;
}, 31 );

add_action( 'woocommerce_after_checkout_validation', function( $fields, $errors ) {
     if ( preg_match( '/\\d/', $fields['billing_first_name'] ) || preg_match( '/\\d/', $fields['billing_last_name'] ) ) {
        $errors->add( 'validation', 'Navn på forelder/foresatt kan ikke innholde sifre.' );
    }
}, 10, 2 );
 

// PHP: Remove "(optional)" from our non required fields
add_filter( '?woocommerce_form_field' , 'remove_checkout_optional_fields_label', 10, 4 );
function remove_checkout_optional_fields_label( $field, $key, $args, $value ) {
    // Only on checkout page
    if( is_checkout() && ! is_wc_endpoint_url() ) {
        $optional = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
        $field = str_replace( $optional, '', $field );
    }
    return $field;
}

// JQuery: Needed for checkout fields to Remove "(optional)" from our non required fields
add_filter( '?wp_footer' , 'remove_checkout_optional_fields_label_script' );
function remove_checkout_optional_fields_label_script() {
    // Only on checkout page
    if( ! ( is_checkout() && ! is_wc_endpoint_url() ) ) return;

    $optional = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
    ?>
    <script>
    jQuery(function($){
        // On "update" checkout form event
        $(document.body).on('update_checkout', function(){
            $('#billing_country_field label > .optional').remove();
            $('#billing_address_1_field label > .optional').remove();
            $('#billing_postcode_field label > .optional').remove();
            $('#billing_state_field label > .optional').remove();
            $('#shipping_country_field label > .optional').remove();
            $('#shipping_address_1_field label > .optional').remove();
            $('#shipping_postcode_field label > .optional').remove();
            $('#shipping_state_field label > .optional').remove();
        });
    });
    </script>
    <?php
}


// Removes Order Notes Title - Additional Information & Notes Field
add_filter( 'woocommerce_enable_order_notes_field', '__return_false', 9999 );

// Remove Order Notes Field
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
     unset ( $fields['order']['order_comments'] );
     return $fields;
} );

add_filter( 'woocommerce_coupons_enabled', function( $enabled) {
//	$enabled = false;
	return $enabled;
} );

/**
 * Changes the redirect URL for the Return To Shop button in the cart.
 *
 */
add_filter( 'woocommerce_return_to_shop_redirect', function() {
    return get_site_url() . '/kursoversikt/';
} );

/**
 * Removes order again button
 */ 
remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );

/**
 * Gets product quantity of product ID
 */ 


//if( !function_exists('show_specific_product_quantity') ) {
//
//    function show_specific_product_quantity( $atts ) {
//
//        // Shortcode Attributes
//        $atts = shortcode_atts(
//            array(
//                'id' => '', // Product ID argument
//            ),
//            $atts,
//            'product_qty'
//        );
//
//        if( empty($atts['id'])) return;
//
//        $stock_quantity = 0;
//
//        $product_obj = wc_get_product( intval( $atts['id'] ) );
//        $stock_quantity = $product_obj->get_stock_quantity();
//
//        if( $stock_quantity > 0 ) return $stock_quantity;
//
//    }
//
//    add_shortcode( 'product_qty', 'show_specific_product_quantity' );
//
//}

//function displayProductName($item) { $productName = get_the_title($item['id']); return $productName; } add_shortcode('product_name', 'displayProductName');

function custom_single_product_image_html( $html, $post_id ) {
    $post_thumbnail_id = get_post_thumbnail_id( $post_id );
    return get_the_post_thumbnail( $post_thumbnail_id, apply_filters( 'single_product_large_thumbnail_size', 'shop_single' ) );
}

add_filter( '?woocommerce_single_product_image_thumbnail_html', 'custom_single_product_image_html', 10, 2);


/**
 * Remove related products output
 */
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
