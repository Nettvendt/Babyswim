<?php
//namespace Babyswim\knutsp;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace all occurrences of the search string with the replacement string.
 *
 * @author Sean Murphy <sean@iamseanmurphy.com>
 * @copyright Copyright 2012 Sean Murphy. All rights reserved.
 * @license http://creativecommons.org/publicdomain/zero/1.0/
 * @link http://php.net/manual/function.str-replace.php
 *
 * @param mixed $search
 * @param mixed $replace
 * @param mixed $subject
 * @param int $count
 * @return mixed
 */

if ( ! function_exists( 'mb_str_replace' ) ) {
	function mb_str_replace( $search, $replace, $subject, int &$count = 0 ) {
		if ( ! is_array( $subject ) ) {
			// Normalize $search and $replace so they are both arrays of the same length
			$searches = is_array( $search ) ? array_values( $search ) : array( $search );
			$replacements = is_array( $replace ) ? array_values( $replace ) : array( $replace );
			$replacements = array_pad( $replacements, count( $searches ), '');

			foreach ( $searches as $key => $search ) {
				$parts = mb_split( preg_quote( $search ), $subject );
				$count += count( $parts ) - 1;
				$subject = implode( $replacements[ $key ], $parts );
			}
		} else {
			// Call mb_str_replace for each subject in array, recursively
			foreach ( $subject as $key => $value ) {
				$subject[ $key ] = mb_str_replace( $search, $replace, $value, $count );
			}
		}

		return $subject;
	}
}

if ( ! function_exists( 'array_key_first' ) ) {
	function array_key_first( array $array ) {
		foreach ( $array as $key => $value ) {
			return $key;
		}
	}
}

/** Experimental
 */

function swim_add_kurs_meta() {
	add_meta_box( 'start', 'Kurs', 'swim_kurs_meta', 'product' );
}
add_action( 'add_meta_boxes', 'swim_add_kurs_meta' );

function swim_kurs_meta( $post ) {
	$meta   = Kursoversikt_Settings::$event_meta;
	uksort( $meta, function( $key1, $key2 ) use ( $meta ) {
		return array_key_first( $meta[ $key1 ]['fieldset'] ) <=> array_key_first( $meta[ $key2 ]['fieldset'] );
	} );
	$names = array_keys( $meta );
	$fieldsets = [];
?>
	<fieldset id="">
		<legend></legend>
		
		<style scoped="scoped">
			.inside fieldset label { vertical-align: unset; }
		</style>
<?php
	foreach ( $names as $key => $name ) {
		$id     = isset( $meta[ $name ]['label_for' ] ) ? $meta[ $name ]['label_for' ] : false;
		$type   = isset( $meta[ $name ]['type'      ] ) ? $meta[ $name ]['type'      ] : false;
		$size   = isset( $meta[ $name ]['size'      ] ) ? $meta[ $name ]['size'      ] : false;
		$maxlen = isset( $meta[ $name ]['maxlength' ] ) ? $meta[ $name ]['maxlength' ] : false;
		$min    = isset( $meta[ $name ]['min'       ] ) ? $meta[ $name ]['min'       ] : false;
		$max    = isset( $meta[ $name ]['max'       ] ) ? $meta[ $name ]['max'       ] : false;
		$unit   = isset( $meta[ $name ]['unit'      ] ) ? $meta[ $name ]['unit'      ] : false;
		$label  = isset( $meta[ $name ]['label'     ] ) ? $meta[ $name ]['label'     ] : false;
		$valtyp = isset( $meta[ $name ]['value_type'] ) ? $meta[ $name ]['value_type'] : false;
		$value  = get_post_meta( $post->ID, $name, true );
		$value  = $type == 'number' || $valtyp == 'number' ? intval( $value ) : esc_attr( $value );
		if ( $type == 'select' ) {
			$options_def = isset( $meta[ $name ]['options'] ) ? $meta[ $name ]['options'] : false;
			if ( $id == 'instructor' ) {
				$current = get_post_meta( $name, true );
?>
				<label for="<?=$id?>"><?=$label?></label>
				<select id="<?=$id?>" name="<?=$name?>">
					<option value=""> - velg <?=strtolower($label)?></option>
<?php
				$object_type   = array_key_first( $options_def );
				$object_args   = $options_def[ $object_type ];
				$option_fields = $options_def[ 'option' ];
				if ( $object_type == 'posts' ) {
					$options = get_posts( $object_args );
					$keys    = wp_list_pluck( $options, $option_fields['value']   );
					$values  = wp_list_pluck( $options, $option_fields['label'] );
				} elseif( $object_type == 'users' ) {
					$options = get_users( $object_args );
					$option_values = wp_list_pluck( $options, $option_fields['value']   );
					$option_labels = wp_list_pluck( $options, $option_fields['label'] );
				}
				foreach ( $option_labels as $i => $option_label ) {
					$option_value = $option_values[ $i ];
					$option_value = $valtyp == 'number' ? intval( $option_value ) : $option_value;
?>
					<option value="<?=$option_value?>"<?=selected($option_value,$value,true,false)?>><?=$option_label?></option>
<?php
				}
?>
				</select>
				<br/>
<?php
			}
		} else {
?>
				<label for="<?=$id?>"><?=$label?>:</label>
				<input id="<?=$id?>" type="<?=$type?>" name="<?=$name?>" size="<?=$size?>" maxlength="<?=$maxlen?>" min="<?=$min?>" max="<?=$max?>" value="<?=$value?>"/><?=$unit?>
				<br/>
<?php
		}
	}
?>
	</fieldset>
<?php
//	}
}

add_action( 'init', function() {
	$labels = [
		'name'              => 'Steder',
		'singular_name'     => 'Sted',
		'add_new_item'      => 'Legg til nytt sted',
		'new_item_name'     => 'Nytt navn på sted',
		'back_to_items'     => 'Tilbake til steder',
		'update_item'       => 'Oppdater sted',
		'view_item'         => 'Vis sted',
		'edit_item'         => 'Rediger sted',
		'all_items'         => 'Alle steder',
		'no_terms'          => 'Ingen steder',
		'parent_item'       => 'Overordnet sted',
		'parent_item_colon' => 'Overordnet sted:',
	];
	$args = [
		'labels'            => $labels,
		'public'            => true,
		'hierarchical'      => true,
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_admin_column' => true,
		'rewrite'           => true,
	];
	register_taxonomy( Kursoversikt::$woo_loc_tax, 'product', $args );
} );

function arstid( $date ): string {
	$offset = 1;	// Min 1
	$arstider = Kursoversikt::seasons;
	$mnd = idate( 'm', $date );
	$mnd = $mnd > 12 - $offset ? 0 : $mnd += $offset - 1;
	$mnd = intdiv( $mnd, 12 / count( $arstider ) );
	return $arstider[ $mnd ];
}

add_action( 'save_post_product', function( $post_id, $post ) {

	$names = array_keys( Kursoversikt_Settings::$event_meta );

	foreach ( $names as $name ) {
		if ( array_key_exists( $name, $_POST ) ) {
			update_post_meta( $post_id, $name, esc_attr( $_POST[ $name ] ) );
		}
	}

	$terms = wp_get_post_terms( $post_id, 'product_cat' );
	$term_count = count( $terms );
	if ( $terms && $term_count && $term_count < 3 ) {	// Not zero, one or two allowed
		$key = $term_count == 2 && $terms[0]->parent == 0 ? 1 : 0; // If we have two and the first one is the target itself, try the second
		$cat = $terms[ $key ];
		$target_cat = Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ];
		if ( $cat->parent == $target_cat ) {	// Selcted cat must be child of target cat
			$terms = wp_get_post_terms( $post_id, Kursoversikt::$woo_loc_tax );
			if ( $terms && count( $terms ) == 1 ) {
				WC_Admin_Notices::remove_all_notices();
				$start   = Kursoversikt::get_event_start_time( $post_id );
				if ( $start > current_time( 'U' ) ) {
					if ( Kursoversikt::$woo_auto_title && strlen( $post->post_title ) < 2 ) {
//						$arstid  = arstid( $start );
						$loc = $terms[0]->name;
						$weeks = 'uke ' . date( 'W', $start ) . '-' . substr( '0' . date( 'W', strtotime( '+' . ( Kursoversikt::$event_times * Kursoversikt::$event_interval - 1 ) . ' days', $start ) ), -2, 2 );
						$end = strtotime( '+' . Kursoversikt::$event_duration . ' minutes', $start );
//						$start_format = $float_time == intval( $float_time ) ? [ 'H', 'Hi' ] : [ 'Hi', 'H' ];
						$postarr = [
							'ID'         => $post_id,
							'post_title' => $loc . ' / ' . date_i18n( 'l j. F Y \k\l H:i', $start ) . '-' . date_i18n( 'H:i', $end ) . ' / ' . $weeks . ' / ' . $cat->name,
						];
						remove_all_actions( 'save_post_product', 11 );
						wp_update_post( $postarr );
					}
				} else {
					WC_Admin_Notices::add_custom_notice( 'error', 'Startdatoen er passert!' );	// Hent fra settings!!
				}
			} else {
				if ( $terms ) {
					$label = strtolower( get_taxonomy_labels( get_taxonomy( Kursoversikt::$woo_loc_tax ) )->name );
					WC_Admin_Notices::add_custom_notice( 'error', 'Kurset har flere ' . $label . '!' );
				} else {
					$label = strtolower( get_taxonomy_labels( get_taxonomy( Kursoversikt::$woo_loc_tax ) )->singular_name );
					WC_Admin_Notices::add_custom_notice( 'error', 'Kurset mangler ' . $label . '!' );
				}
			}
		} elseif ( $cat->term_id == $target_cat ) {
			$label = strtolower( get_taxonomy_labels( get_taxonomy( 'product_cat' ) )->name );
			WC_Admin_Notices::add_custom_notice( 'error', 'Kurset har ingen gyldige ' . $label . '!' );
		}
	} elseif ( $terms && ( $terms[0]->term_id == $target_cat || $terms[0]->parent = $target_cat ) ) {
		$label = strtolower( get_taxonomy_labels( add_men( 'product_cat' ) )->name );
		WC_Admin_Notices::add_custom_notice( 'error', 'Kurset har flere ' . $label . '.' );
	}
}, 11, 2 );

add_action( 'woocommerce_product_meta_start', function() {
	global $post;
	$instructor = intval( get_post_meta( $post->ID, Kursoversikt::pf . 'instructor', true ) );
	$instructor = get_user_by( 'ID', $instructor )->display_name;
	echo '<span class="posted_in instructor" style="display: block;">', Kursoversikt::$instructor_role['instructor'], ': <a>', $instructor, '</a></span>';
} );

add_action( 'admin_notices', function() {
//	var_dump( WC_Admin_Notices::get_notices() );
	WC_Admin_Notices::remove_notice( 'error' );
	WC_Admin_Notices::remove_notice( 'warning' );
}, 9999 );

add_action( 'admin_menu', function() {
	global $twl_instance;
	if ( ! in_array( 'administrator', wp_get_current_user()->roles ) ) {
		remove_menu_page( TWL_CORE_OPTION_PAGE );
		add_menu_page( __( 'Twilio', TWL_TD ), __( 'Twilio', TWL_TD ), 'manage_options', TWL_CORE_OPTION_PAGE, [ $twl_instance, 'display_tabs' ], 'dashicons-email-alt', 91 );
	}
}, 1001 );

add_filter( 'post_thumbnail_html', function( $html, $post_id , $thumbnail_id, $size, $attr ) {
	if ( get_post_type( $post_id ) == 'product' && empty( $html ) ) {
		$terms = get_the_terms( $post_id, 'product_cat' );
		if ( isset( $terms[0] ) && count( $terms ) == 1 ) {
			do_action( 'begin_fetch_post_thumbnail_html', $post_id, $thumbnail_id, $size );
			$html = wp_get_attachment_image( intval( get_term_meta( $terms[0]->term_id, 'thumbnail_id', true ) ), $size, false, $attr );
			if ( in_the_loop() ) {
				update_post_thumbnail_cache();
			}
			do_action( 'end_fetch_post_thumbnail_html', $post_id, $thumbnail_id, $size );
		}
	}
	return $html;
}, 10, 5 );

add_filter( 'woocommerce_cart_item_thumbnail', function( $html, $cart_item ) {
	$html = get_the_post_thumbnail( $cart_item['product_id'], 'post_thumbnail', [ 'class' => 'attachment-shop_thumbnail wp-post-image' ] );
	return $html;
}, 10, 2 );

add_filter( 'woocommerce_shop_manager_editable_roles', function( $roles ) {
    $roles[] = 'instructor';
    return $roles;
} );

add_filter( 'manage_edit-product_columns', function( $columns ) {
	$columns['sku']         = 'Varenr';
	$columns['is_in_stock'] = 'Plasser';
	$columns['instructor']  = 'In';
	$columns['taxonomy-' . Kursoversikt::$woo_loc_tax ] = 'Sted';
	$tag  = $columns['product_tag'];
	unset( $columns['product_tag'] );
	$feat = $columns['featured'];
	unset( $columns['featured'] );
	$date = $columns['date'];
	unset( $columns['date'] );
	$columns['qty_in_carts'] = '<abbr title="Antall reservert i handlekurver.">R</abbr>';
	$columns['product_type'] = '<abbr title="Er virtuell?">V</abbr>';
	$columns['product_tag']  = $tag;
	$columns['featured']     = $feat;
	$columns['date']         = $date;
	return $columns;
}, 30 );

add_action( 'manage_product_posts_custom_column', function( $column, $post_id ) {
	if ( $column == 'product_type' ) {
		echo wc_get_product( $post_id )->get_virtual() ? '&#x2714;' : '–';
	} elseif ( $column == 'instructor' ) {
		$instructor = get_user_by( 'ID', get_post_meta( $post_id, Kursoversikt::pf . 'instructor', true ) );
		echo '<abbr title="', $instructor->display_name, '."><a href=".?page=deltakere&amp;event=', $post_id,'">', mb_substr( $instructor->first_name, 0, 1 ) . mb_substr( $instructor->last_name, 0, 1 ), '</a></abbr>';
	}
}, 10, 2 );

add_filter( 'manage_users_columns', function( $columns ) {
	$email = $columns['email'];
	$role  = $columns['role' ];
	$posts = $columns['posts'];
	unset ( $columns['name' ] );
	unset ( $columns['email'] );
	unset ( $columns['role' ] );
	unset ( $columns['posts'] );
	$columns['display_name'] = 'Navn';
	$columns['email'       ] = $email;
	$columns['role'        ] = $role;
	$columns['posts'       ] = $posts;
	$columns['orders'      ] = 'Påmld.';
	return $columns;
}, 1 );

add_filter( 'manage_users_columns', function( $columns ) {
	if ( $_GET['role'] == 'customer' ) {
		unset ( $columns['tfa-status'] );
	}
	return $columns;
}, 11 );

add_filter( 'manage_users_custom_column', function( $value, $column, $user_id ) {
	if ( $column == 'display_name' ) {
		$user   = get_user_by( 'ID', $user_id );
		$value  = $user->first_name . ' ' . $user->last_name;
		$name   = $user->display_name;
		$value .= $name == $user->user_login || ( $value != $name && $name != get_user_meta( $user_id, 'nickname', true ) ) ? '<br />' . $name : '';
	} elseif ( $column == 'orders' ) {
		$value = wc_get_customer_order_count( $user_id );
	}
	return $value;
}, 10, 3 );

add_action( 'admin_head', function() {
?>
<style>
	.woocommerce-Price-currencySymbol:after  { content: ' '; }
	table.wp-list-table.posts .column-sku          { width: 5.5em; }
	table.wp-list-table.posts .column-price        { white-space: nowrap; }
	table.wp-list-table.posts .column-product_tag  { width: 4em !important; }
	table.wp-list-table.posts .column-product_type { width: 1em; }
	table.wp-list-table.posts .column-instructor   { width: 2em; }
	table.wp-list-table.posts .column-qty_in_carts { width: 1em; }
	table.wp-list-table.posts .column-taxonomy-webfacing-events-location { width: 4em; }
	table.wp-list-table.users .column-role   { width: 8.5em; }
	table.wp-list-table.users .column-orders { width: 3em; }
	table.wp-list-table.users .column-wp-last-login { width: 10em; }
	table.wp-list-table.users .column-tfa-status    { width: 2em; }
</style>
<?php
} );

add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );	// Connect/subscription nag

include 'woo-checkout-functions.php';