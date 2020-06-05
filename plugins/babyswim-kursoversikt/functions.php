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

function swim_dump( ...$var ) {
	if ( WP_DEBUG ) {
		echo PHP_EOL, '<pre>';
		var_dump( ...$var );
		echo '</pre>', PHP_EOL;
	}
}

function swim_export( $var ) {
	if ( WP_DEBUG ) {
		echo PHP_EOL, '<pre>';
		echo str_replace( [ 'array (' . PHP_EOL . ' ', ')' ], [ '[', ']' ], var_export( $var, true ) );
		echo '</pre>', PHP_EOL;
	}
}

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

if ( ! function_exists( 'str_contains' ) ) {
	function str_contains( string $haystack, string $needle ): bool {
		return strpos( $haystack, $needle ) !== false;
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
    /**
     * Convenient way to check if a string starts with another string.
     *
     * @param string $haystack String to search through.
     * @param string $needle Pattern to match.
     * @return bool Returns true if $haystack starts with $needle.
     */
    function str_starts_with( string $haystack, string $needle ): bool {
        $length = strlen( $needle );
        return substr( $haystack, 0, $length ) === $needle;
    }
}

if ( ! function_exists( 'str_ends_with' ) ) {
    /**
     * Convenient way to check if a string ends with another string.
     *
     * @param string $haystack String to search through.
     * @param string $needle Pattern to match.
     * @return bool Returns true if $haystack ends with $needle.
     */
    function str_ends_with( string $haystack, string $needle ): bool {
        $length = strlen( $needle );
        return substr( $haystack, -$length ) === $needle;
    }
}

if ( ! function_exists( 'get_browser_name' ) ) {
	function get_browser_name( string $user_agent ): string {
		if ( str_contains( $user_agent, 'Opera') || str_contains( $user_agent, 'OPR/') ) return 'Opera';
		elseif ( str_contains( $user_agent, 'Edge' ) ) return 'Edge';
		elseif ( str_contains( $user_agent, 'Chrome' ) ) return 'Chrome';
		elseif ( str_contains( $user_agent, 'Safari' ) ) return 'Safari';
		elseif ( str_contains( $user_agent, 'Firefox')) return 'Firefox';
		elseif ( str_contains( $user_agent, 'MSIE') || str_contains( $user_agent, 'Trident/7') ) return 'Internet Explorer';
		return 'Other';
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

add_action( 'save_post_page', function( $post_id ) {
	$transient_name = Kursoversikt::pf . 'events_' . $post_id;
	delete_transient( $transient_name );
}, 10, 1 );

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
		if ( ! wp_is_post_revision( $post_id ) && $cat->parent == $target_cat ) {	// Selcted cat must be child of target cat
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
							'post_name'  => wp_unique_post_slug( date_i18n( 'ymdHi', $start ) . '-' . $terms[0]->slug . '-' . $cat->slug, $post_id, $post->post_status, $post->post_type, $post->post_parent ), 
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
//	$feat = $columns['featured'];
	unset( $columns['featured'] );
	$date = $columns['date'];
	unset( $columns['date'] );
	$columns['qty_in_carts'] = '<abbr title="Antall reservert i handlekurver.">R</abbr>';
	$columns['product_type'] = '<abbr title="Er virtuell?">V</abbr>';
	$columns['product_tag']  = $tag;
//	$columns['featured']     = $feat;
	$columns['date']         = $date;
	return $columns;
}, 30 );

add_action( 'manage_product_posts_custom_column', function( $column, $post_id ) {
	if ( $column == 'product_type' ) {
		echo wc_get_product( $post_id )->get_virtual() ? '&#x2714;' : '–';
	} elseif ( $column == 'instructor' ) {
		$instructor = get_user_by( 'ID', get_post_meta( $post_id, Kursoversikt::pf . 'instructor', true ) );
		$loc_id = get_the_terms( $post_id, Kursoversikt::$woo_loc_tax )[0]->term_id;
		echo '<abbr title="', $instructor->display_name, '."><a href=".?page=deltakere&amp;event=', $post_id, '#printarea-', $loc_id, '">', mb_substr( $instructor->first_name, 0, 1 ) . mb_substr( $instructor->last_name, 0, 1 ), '</a></abbr>';
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
		$value  = str_contains( $user->user_login, ' ' ) ? '<sub style="color: red; font-size: xx-large; font-weight: bold;">&larr;</sub> ' . $value : $value;
	} elseif ( $column == 'orders' ) {
		$value = '<a href="' . add_query_arg( [ 'post_type' => 'shop_order', '_customer_user' => $user_id ], '/wp-admin/edit.php' ) . '">' . wc_get_customer_order_count( $user_id ) . '</a>';
	}
	return $value;
}, 10, 3 );

/**
 * Change a currency symbol
 */
add_filter('woocommerce_currency_symbol', function( string $symbol, string $currency ): string {
	if ( in_array( $currency, [ 'NOK' ] ) ) {
		$symbol .= '&nbsp;';
	}
	return $symbol;
}, 10, 2 );

add_action( 'admin_head', function() {
?>
	<style>
/*		.woocommerce-Price-currencySymbol:after  { content: ' '; }*/
		.post-type-shop_order .wp-list-table tbody td { padding: 1px 10px; line-height: 125%; }
		.post-type-shop_order .wp-list-table .column-participants { width: 13ch; }
		.post-type-shop_order .wp-list-table .column-order_total { width: 4ch; }
		.post-type-shop_order .wp-list-table .column-wc_actions { width: 6ch; }
		.post-type-shop_order .wp-list-table .column-wc_actions .wc-action-button-archive::after { font-family: woocommerce; content: "\e006"; }
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
//	var_dump( get_current_screen() );
	if ( function_exists( 'get_current_screen' ) && get_current_screen()->base == 'toplevel_page_wp-help-documents' ) {
?>
	<style>
		html { height: auto; }
	</style>
<?php
	}
} );

add_filter( 'woocommerce_email_styles', function( $css ) {
	$css .= PHP_EOL . 'td span span:after { content: " "; }';
	return $css;
}, 11 );

add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );	// Connect/subscription nag

add_action( 'wp_user_profiles_save', function( $user_id ) {
//	$user = $user ? $user : get_user_by( 'ID', Kursoversikt::user_id() );
	if ( $user_id ) {
		if ( class_exists( 'WC_Admin_Profile' ) ) {
			$wc_admin_profile = new WC_Admin_Profile;
//			var_dump( $user_id );
			call_user_func( [ $wc_admin_profile, 'save_customer_meta_fields' ], $user_id );
		}
	}
}, 99, 1 );	// MUST BE over 10

add_filter( 'woocommerce_customer_meta_fields', function( $fields ) {
	$address1 = $fields['billing']['fields']['billing_address_1'];
	$postcode = $fields['billing']['fields']['billing_postcode' ];
	$city     = $fields['billing']['fields']['billing_city'     ];
	$phone    = $fields['billing']['fields']['billing_phone'    ];
	$email    = $fields['billing']['fields']['billing_email'    ];
	unset (
		$fields['billing']['fields']['billing_company'   ],
		$fields['billing']['fields']['billing_postcode'  ],
		$fields['billing']['fields']['billing_address_1' ],
		$fields['billing']['fields']['billing_address_2' ],
		$fields['billing']['fields']['billing_city'      ],
		$fields['billing']['fields']['billing_state'     ],
		$fields['billing']['fields']['billing_country'   ],
		$fields['billing']['fields']['billing_phone'     ],
		$fields['billing']['fields']['billing_email'     ],
	);
	$fields['billing']['fields']['billing_address_1'] = $address1;
	$fields['billing']['fields']['billing_postcode' ] = $postcode;
	$fields['billing']['fields']['billing_city'     ] = $city;
	$fields['billing']['fields']['billing_email'    ] = $email;
	$fields['billing']['fields']['billing_phone'    ] = $phone;
	$fields['shipping'] = [];
	return $fields;
} );

function swim_user_profile_meta_box( $user ) {
	( new WC_Admin_Profile )->add_customer_meta_fields( $user );
}

function swim_user_profile_orders_box( $user ) {
	$user_id = $user ? $user->ID : Kursoversikt::user_id();
	$user = $user ?: get_user_by( 'id', $user_id );
	$args = [
		'customer_id' => $user_id,
		'limit'       => 30,
		'return'      => 'ids',
	];
	$order_ids = wc_get_orders( $args );
	$args = [
		'customer_id' => $user_id,
		'limit'       => 20,
	];
	$orders = wc_get_orders( $args );
	$args = [
		'customer'    => $user->user_email,
		'exclude'     => $order_ids,
		'limit'       => 10,
	];
	$orders = array_merge( $orders, wc_get_orders( $args ) );
	if ( count( $orders ) ) {
		echo PHP_EOL, '<ul>';
		foreach ( $orders as $order ) {
			echo PHP_EOL, ' <li><strong><a href="', $order->get_edit_order_url( $order ), '">', mysql2date( get_option( 'date_format' ), $order->get_date_created() ), '</a> &ndash; Status: ', wc_get_order_status_name( $order->get_status() ), '</strong>';
			echo PHP_EOL, '  <ol>';
			foreach ( $order->get_items() as $order_item ) {
				echo PHP_EOL, '<li>', $order_item->get_name() . ' &times; ' . $order_item->get_quantity(), '</li>';
			}
			foreach( $order->get_used_coupons() as $coupon_code ) {
				echo PHP_EOL, '<li>Kupong: ', $coupon_code, '</li>';
			}
			echo PHP_EOL, '  </ol>';
			echo PHP_EOL, ' </li>';
		}
		echo PHP_EOL, '<ul>';
	} else {
		echo '<p>Ingen ordre hittil.</p>';
	}
}

function swim_user_profile_participants_box( $user ) {
	echo PHP_EOL, '<a href="', add_query_arg( [ 'page' => 'kontrolliste', 'instr' => $user->ID ], '/wp-admin/index.php' ), '">Deltakeroversikt</a>';
}

add_action( 'admin_menu', function() {
	$user_id = Kursoversikt::user_id();
	if ( class_exists( 'WP_User_Profile_Other_Section' )/* &&
		( in_array( 'customer', get_user_by( 'id', $user_id )->roles ) || in_array( 'instructor', get_user_by( 'id', $user_id )->roles ) )*/ )
	{
		new WP_User_Profile_Section( [
			'id'    => 'woo',
			'slug'  => 'woo',
			'name'  => 'WooCommerce',
			'cap'   => 'edit_profile',
			'icon'  => 'dashicons-admin-generic',
			'order' => 84,
		] );

		add_meta_box( 'woo-meta', 'Adresser', 'swim_user_profile_meta_box', 'users_page_woo', 'normal', 'default', 1 );
		if ( in_array( 'instructor', get_user_by( 'id', $user_id )->roles ) ) {
			add_meta_box( 'woo-participants', 'Deltakeroversikt', 'swim_user_profile_participants_box', 'users_page_woo', 'normal', 'default', 1 );
		}
		add_meta_box( 'woo-orders', '<strong><a style="font-weight: inherit; font-size: inherit; text-decoration: none;" href="' . add_query_arg( [ 'post_type' => 'shop_order', '_customer_user' => $user_id ], '/wp-admin/edit.php' ) . '">Alle ordre</a></strong>', 'swim_user_profile_orders_box', 'users_page_woo', 'normal', 'default', 1 );
	}
}, 10 );	// MUST be priority 10!


add_action( 'wp_user_profiles_add_meta_boxes', function( $hook, $user ) {
	class_exists( 'WC_Admin_Profile' );	// Triggers WC user meta (not any more)
}, 99, 2 );

add_action( 'plugins_loaded', function() {
	add_action( 'show_user_profile', [] );	// To get the extra tab in user_profiles plugin
	add_action( 'edit_user_profile', [] );
} );

add_action( 'x-updated_post_meta', function ( $meta_id, $object_id, $meta_key ) {
	$u = '_';
	if ( get_post_type( $object_id ) === 'shop_order' && ( str_contains( $meta_key, $u . 'fodt' . $u ) || str_contains( $meta_key, $u . 'navn' . $u ) ) ) {
		$parts = explode( $u, $meta_key );
		$transient_name = Kursoversikt::pf . 'rc' . $u . $object_id . $u . $parts[0] . $u . $parts[2];
		delete_transient( $transient_name );
//		error_log( "Transient '{$transient_name}' deleted." );
		$order = wc_get_order( $object_id );
		$order_items = $order->get_items();
		foreach( $order_items as $order_item_id => $order_item ) {
			$event_id  = $order_item->get_product_id();
			$quantity  = $order_item->get_quantity();
			for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
//				$key = $quantity == 1 ? 'Deltaker' : 'Tvilling ' . $child_id;
				$key = 'partcipant';
				wc_update_order_item_meta( $order_item_id, $key, get_post_meta( $object_id, $event_id . '_fodt_' . $child_id, true ) . ' ' . get_post_meta( $object_id, $event_id . '_navn_' . $child_id, true ) );
			}
		}
	}
}, 10, 3 );

function kjm_display_last_notice( $atts = [], $content = null, $tag = '' ) {
	$html = '';
	$meta_query = [ [ 'key' => 'kjm_admin_notices_show_frontend', 'value' => true, 'type' => 'NUMERIC' ] ];
	$posts = get_posts( [ 'post_type' => 'kjm_notice', 'post_status' => 'publish', 'meta_query' => $meta_query, 'posts_per_page' => 1 ] );
	$hidden = count( $posts ) ? '' : 'display: none; ';
	$post = $posts[0];
	$hide_title = get_post_meta( $post->ID, 'kjm_admin_notices_hide_title',       true );
	$hide_metas = get_post_meta( $post->ID, 'kjm_admin_notices_hide_metas',       true );
	$bg_color   = get_post_meta( $post->ID, 'kjm_admin_notices_custom_color_bg',  true );
	$color      = get_post_meta( $post->ID, 'kjm_admin_notices_custom_color_txt', true );
	$class = 'kjm-notice-' . get_the_terms( $post->ID, 'kjm_notice_cat' )[0]->slug;
	$html = '<div class="' . $class . '" style="' . $hidden . 'font-weight: bold; background-color: #' . $bg_color . ' !important; color: #' . $color . ' !important; padding: 1em;">';
	$html .= $hide_title ? '' : '<h1 style="margin-bottom: 0;">' . get_the_title( $post ) . '</h1>';
	$html .= $hide_metas ? '<p></p>' : '<address style="font-size: small; font-weight: normal; font-style: italic;">For ' . human_time_diff( mysql2date( 'U', $post->post_date ) ) . ' siden av ' . get_userdata( $post->post_author )->display_name . '</address>';
	$html .= wpautop( get_the_content( null, false, $post ) );
	$html .= '<p>Hilsen ' . get_bloginfo() . '!</p>';
	$html .= '</div>';
	return $html;
}

add_shortcode( 'kjm_display_last_notice', 'kjm_display_last_notice' );

add_filter( 'kjm_admin_notices_frontend_notice_show', function( $show, $page_id ) {
	if ( $page_id ) {
		$post = get_post( $page_id );
		if ( $post ) {
			$show = $show && ! ( has_shortcode( $post->post_content, 'kjm_display_last_notice' ) || has_block( 'block-lab/siste-merknad', $post ) );
		}
	}
	return $show;
}, 10, 2 );

add_action( 'xwoocommerce_product_query', function( $query ) {
//	if ( $query->query_vars['wc_query'] == 'product_query' ) {
	if ( is_admin() ) {
		$query->set( 'status', [ 'pending', 'publish', 'future' ] );
		error_log( 'loop ' . print_r( $query, true ) );
	}
//	return $query;
}, 10 );

add_filter( 'xwoocommerce_product_data_store_cpt_get_products_query', function( $wp_query_args ) {
	error_log( 'q ' . print_r( $wp_query_args, true ) );
	return $wp_query_args;
} );

add_filter( 'kjm_admin_notices_admin_notice_show', function( $show ) {
	$show = get_current_screen()->base == 'dashboard';
//	var_dump( get_current_screen() );
	return $show;
} );

add_filter( 'wp_mail_from', function( $from_email ) {
	$from_email = get_option( 'woocommerce_email_from_address', get_bloginfo( 'admin_email' ) );
	return $from_email;
} );

add_filter( 'wp_mail_from_name', function( $mail_from_name ) {
	$mail_from_name = get_option( 'woocommerce_email_from_name', get_bloginfo() );
	return $mail_from_name;
} );

include_once 'woo-checkout-functions.php';
include_once 'e-mails.php';
include_once 'activity-trash.php';
include_once 'sitemaps.php';
include_once 'refund.php';
include_once 'archive.php';
