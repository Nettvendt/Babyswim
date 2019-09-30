<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add new fields based on quantity
 */ 
add_action( 'woocommerce_after_order_notes', 'babyswim_checkout_fields', 20, 1 );
function babyswim_checkout_fields( $checkout ) {
	$name = $checkout->get_posted_address_data( 'first_name', 'billing' ) .  ' ' . $checkout->get_posted_address_data( 'last_name', 'billing' );
	$name = empty( trim( $name ) ) ? $checkout->get_posted_address_data( 'billing_first_name' ) . ' ' . $checkout->get_posted_address_data( 'billing_last_name' ) : $name;
	$name = empty( trim( $name ) ) ? $checkout->get_value( 'billing_first_name' ) . ' ' . $checkout->get_value( 'billing_last_name' ) : $name;
	$name = empty( trim( $name ) ) ? 'du' : $name;
    echo PHP_EOL, '<div id="babyswim_checkout_fields"><h2>Informasjon om barnet/barna ', $name, ' er forelder til</h2>';
	echo PHP_EOL, '<p><small>Skal barn til andre enn ', $name, ' meldes på <strong>må</strong> det gjøres i en egen påmelding, etter at denne påmeldingen er sendt inn/betalt eller kansellert.</small></p>';

	$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
	$field_base_names = array_keys( $fields );

	// First Loop go through cart items
    foreach( WC()->cart->get_cart() as $cart_item ) {
        // 2nd Loop go through each unit related to item quantity
		$quantity = $cart_item['quantity'];
        for( $child_id = 1; $child_id <= $quantity; $child_id++ ) {

			$item_id = 0; //array_key_first( $field_base_names );	// First field
			$field_name = $cart_item['product_id'] . '_' . $field_base_names[ $item_id ] . '_' . $child_id;
			$product_name = $cart_item['data']->get_name();
            woocommerce_form_field( $field_name, [
                'type'        => 'text',
                'class'       => [ $field_base_names[ $item_id ], 'form-row-first' ],
                'label'       => 'Hva heter' . ( $quantity > 1 ? ' det ' . $child_id . '.' : '' ) . ' barnet på &laquo;' . $product_name . '&raquo;?',
                'placeholder' => $fields[ $field_base_names[ $item_id ] ],
				'required'    => true,

            ], $checkout->get_value( $field_name ) );

			//$item_id = n (#2 to #n-1 ) Other fields not yet defined, for future
			
			$item_id = count( $field_base_names ) - 1; //array_key_last( $field_base_names );	// Last field
			$field_name = $cart_item['product_id'] . '_' . $field_base_names[ $item_id ] . '_' . $child_id;
//			$product_name = $cart_item['data']->get_name();	// Ubrukt her
			woocommerce_form_field( $field_name, [
				'type'        => 'date',
				'class'       => [ $field_base_names[ $item_id ], 'form-row-last' ],
                'label'       => '<br /><br />Når er' . ( $quantity > 1 ? ' det ' . $child_id . '.' : '' ) . ' barnet her født?',
//				'placeholder' => $fields[ $field_base_names[ $item_id ] ],
				'required'    => true,
			], $checkout->get_value( $field_name ) );

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
	$ti_fields = [ 'navn' => 'first_name', 'fodt' => 'last_name' ]; // Transform to Tickera fields
	$order = wc_get_order( $order_id );
	$order_items = $order->get_items();
	foreach ( $order_items as $order_item ) {
		$ticket_id = $order_item->get_product_id();
		$quantity = $order_item->get_quantity();
		for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
			foreach ( $field_base_names as $field_base_name ) {
				$field_name = $ticket_id . '_' . $field_base_name . '_' . $child_id;
				if ( ! empty( $_POST[ $field_name ] ) ) {
					update_post_meta( $order_id, $field_name, esc_attr( $_POST[ $field_name ] ) );
				}
			}
		}
		$meta_query = [ [
			'key'     => 'ticket_type_id',
			'value'   => $ticket_id,
			'compare' => 'NUMERIC',
		] ];
		$ticket_instances_query = new WP_Query( [
			'post_type'      => 'tc_tickets_instances',
			'post_parent'    => $order_id,
			'meta_query'     => $meta_query,
			'no_found_rows'  => true,
			'posts_per_page' => $quantity,
		] );
		$ticket_instances = $ticket_instances_query->posts;
		foreach ( $ticket_instances as $instance_id => $ticket_instance ) {
			$child_id = $instance_id + 1;
			foreach ( $fields as $field_base_name => $field_label ) {
				$ti_field = $ti_fields[ $field_base_name ];
				$field_name = $ticket_id . '_' . $field_base_name . '_' . $child_id;
				if ( ! empty( $_POST[ $field_name ] ) ) {
					update_post_meta( $ticket_instance->ID, $ti_field, esc_attr( $_POST[ $field_name ] ) );
				}
			}
		}
    }
}

/**
 * Display the custom-field in orders view
 */
add_action( 'woocommerce_order_details_after_order_table', 'display_my_custom_field_in_orde_details' );

function display_my_custom_field_in_orde_details( $order ) {
	$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
//	$field_base_names = array_keys( $fields ); // Not needed
	$order_id = $order->get_id();
	$order_items = $order->get_items();

    ?>
	<section class="woocommerce-order-details">
		<h2 class="woocommerce-order-details__title">Deltakere</h2>
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
<?php
}

// PHP: Remove "(optional)" from our non required fields
add_filter( 'woocommerce_form_field' , 'remove_checkout_optional_fields_label', 10, 4 );
function remove_checkout_optional_fields_label( $field, $key, $args, $value ) {
    // Only on checkout page
    if( is_checkout() && ! is_wc_endpoint_url() ) {
        $optional = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
        $field = str_replace( $optional, '', $field );
    }
    return $field;
}

// JQuery: Needed for checkout fields to Remove "(optional)" from our non required fields
add_filter( 'wp_footer' , 'remove_checkout_optional_fields_label_script' );
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
add_filter( 'woocommerce_checkout_fields' , 'remove_order_notes' );

function remove_order_notes( $fields ) {
     unset($fields['order']['order_comments']);
     return $fields;
}

add_filter( 'woocommerce_coupons_enabled', function( $enabled) {
//	$enabled = false;
	return $enabled;
} );

/**
 * Changes the redirect URL for the Return To Shop button in the cart.
 *
 * @return string
 */
function wc_empty_cart_redirect_url() {
    return get_site_url() . '/kursoversikt/';
}

/**
 * Removes order again button
 */ 

remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );

/**
 * Gets product quantity of product ID
 */ 


add_filter( 'woocommerce_return_to_shop_redirect', 'wc_empty_cart_redirect_url' );


if( !function_exists('show_specific_product_quantity') ) {

    function show_specific_product_quantity( $atts ) {

        // Shortcode Attributes
        $atts = shortcode_atts(
            array(
                'id' => '', // Product ID argument
            ),
            $atts,
            'product_qty'
        );

        if( empty($atts['id'])) return;

        $stock_quantity = 0;

        $product_obj = wc_get_product( intval( $atts['id'] ) );
        $stock_quantity = $product_obj->get_stock_quantity();

        if( $stock_quantity > 0 ) return $stock_quantity;

    }

    add_shortcode( 'product_qty', 'show_specific_product_quantity' );

}

function displayProductName($item) { $productName = get_the_title($item['id']); return $productName; } add_shortcode('product_name', 'displayProductName');

function custom_single_product_image_html( $html, $post_id ) {
    $post_thumbnail_id = get_post_thumbnail_id( $post_id );
    return get_the_post_thumbnail( $post_thumbnail_id, apply_filters( 'single_product_large_thumbnail_size', 'shop_single' ) );
}

add_filter( 'woocommerce_single_product_image_thumbnail_html', 'custom_single_product_image_html', 10, 2);


/**
 * Remove related products output
 */
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
