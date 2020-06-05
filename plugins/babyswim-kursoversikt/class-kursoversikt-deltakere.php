<?php
//namespace Babyswim\knutsp;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kursoversikt_Deltakere {

	/**
	 * Woocommerce extra special checkout fields definition [ 'name' => 'Label' ] accessed by self::WC_ESC_FIELDS or Kursoversikt_Deltakere::WC_ESC_FIELDS
	 */
	const WC_ESC_FIELDS = [
		'navn' => 'Fornavn',
		'fodt' => 'Født',
	];

	public static function get_order_ids_by_product( int $product_id, array $statuses = [ 'completed', 'processing', 'on-hold' ], string $ordering = 'DESC' ): array {
		global $wpdb;
		$prefix = 'wc-';

		$table_posts    = $wpdb->prefix . "posts";
		$table_items    = $wpdb->prefix . "woocommerce_order_items";
		$table_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";

//		$statuses = "'wc-completed', 'wc-processing', 'wc-on-hold'";
		$statuses = "'{$prefix}" . implode( "', '{$prefix}", $statuses ) . "'";

		$orders_ids = $wpdb->get_col( "
			SELECT $table_items.order_id
			FROM $table_itemmeta, $table_items, $table_posts
			WHERE $table_items.order_item_id = $table_itemmeta.order_item_id
			AND $table_items.order_id = $table_posts.ID
			AND $table_posts.post_status IN ( $statuses )
			AND $table_itemmeta.meta_key LIKE '_product_id'
			AND $table_itemmeta.meta_value LIKE '$product_id'
			ORDER BY $table_items.order_item_id $ordering"
		);
		$orders_ids = is_array( $orders_ids ) ? $orders_ids : [];
		return $orders_ids;
	}

	public function show_not_before_participants() {
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', function( $query, $query_vars ) {
			$key = Kursoversikt::pf . 'start';
			if ( ! empty( $query_vars[ $key ] ) ) {
				$query['meta_query'][] = [
					'key'     => $key,
					'value'   => esc_attr( $query_vars[ $key ] ),
					'compare' => '>=',
					'type'    => 'DATE',
				];
			}
			return $query;
		}, 10, 2 );

		$outofstock_only  = isset( $_GET['outofstock-only' ] ) && $_GET['outofstock-only' ];
		$not_participated = isset( $_GET['not-participated'] ) && $_GET['not-participated'];
		$pending_only     = isset( $_GET['pending-only'    ] ) && $_GET['pending-only'    ];
		$fresh_only       = isset( $_GET['fresh-only'      ] ) && $_GET['fresh-only'      ];
		$instr = current_user_can( 'edit_others_shop_orders' ) ? ( isset( $_GET['instr'] ) ? intval( $_GET['instr'] ) : false ) : get_current_user_id();
		echo PHP_EOL, '<div class="wrap">';
		echo PHP_EOL, ' <h1>', get_admin_page_title(), ' - ', esc_attr( $_GET['page'] ), '</h1>';
		echo PHP_EOL, ' <p><br /></p>';
		$cats = get_terms( [
			'taxonomy' => Kursoversikt::$woo_event_tax,
			'parent'   => Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ]
		] );
		$cats = wp_list_pluck( $cats, 'slug' );
		$args = [
			'status'                   => $pending_only ? [ 'pending', 'future' ] : [ 'publish' ],
			Kursoversikt::pf . 'start' => date_i18n( 'Y-m-d', Kursoversikt::get_upcoming_events_from() ),
			'category'                 => $cats,
			'stock_status'             => $outofstock_only ? 'outofstock' : null,
			'instructor'               => $instr ?: null,
			'limit'                    => 999,
		];
		$transient_name = Kursoversikt::pf . md5( serialize( $args ) );
		$transient_time = WP_DEBUG ? 2 * MINUTE_IN_SECONDS : Kursoversikt::transient_time * HOUR_IN_SECONDS;
		$events = get_transient( $transient_name );
		$is_fresh = $fresh_only || ! is_array( $events ) || ! count( $events );
		if ( $is_fresh ) {
			add_filter( 'woocommerce_product_data_store_cpt_get_products_query', function( $query, $query_vars ) {
				if ( isset( $query_vars['instructor'] ) ) {
					$query['meta_query'][] = [
						'key'   => Kursoversikt::pf . 'instructor',
						'value' => intval( $query_vars['instructor'] ),
					];
				}
				return $query;
			}, 10, 2 );
			$events = wc_get_products( $args );
			usort ( $events, function( $a, $b ) {
				$aloc_id = str_pad( get_the_terms( $a->get_id(), Kursoversikt::$woo_loc_tax )[0]->term_id, 8, '0', STR_PAD_LEFT );
				$bloc_id = str_pad( get_the_terms( $b->get_id(), Kursoversikt::$woo_loc_tax )[0]->term_id, 8, '0', STR_PAD_LEFT );
				$astart_dt = date_i18n( 'wHi', Kursoversikt::get_event_start_time( $a->get_id() ) );
				$bstart_dt = date_i18n( 'wHi', Kursoversikt::get_event_start_time( $b->get_id() ) );
				return $aloc_id . $astart_dt <=> $bloc_id . $bstart_dt;
			} );
			set_transient( $transient_name, $events, $transient_time );
		}
		echo PHP_EOL, ' <form action="', $_SERVER['REQUEST_URI'], '">';
		$instructors   = get_users( [ 'role' => 'instructor' ] );
		echo PHP_EOL, '  <label for="instr">Instruktør</label>';
		echo PHP_EOL, '  <select id="instr" name="instr" title="Antall: ', count( $instructors ),'.">';
		if ( current_user_can( 'edit_others_shop_orders' ) ) {
			echo PHP_EOL, '   <option value="0"', selected( false, $instr, false ), '> -- alle instruktører -- </option>';
			foreach ( $instructors as $instructor ) {
				$instructor_id = intval( $instructor->ID );
				echo PHP_EOL, '<option value="', $instructor_id, '"', selected( $instructor_id, $instr, false ), ' title="Instruktør-ID: ', $instructor_id, '.">', $instructor->display_name, '</option>';
			}
		} else {
				echo PHP_EOL, '<option value="', $instr, '"', selected( true ), ' ' , disabled( true ), ' title="Instruktør-ID: ', $instr, '.">', get_user_by( 'id', $instr )->display_name, '</option>';
		}
		echo PHP_EOL, '  </select> &nbsp; ';
		echo PHP_EOL, '  <input id="outofstock-only" type="checkbox" name="outofstock-only"', checked( $outofstock_only ), ' value="1" /><label for="outofstock-only" title="Vis kun fulltegnede kurs.">Kun fulltegnede &nbsp; </label>';
		echo PHP_EOL, '  <input id="not-participated" type="checkbox" name="not-participated"', checked( $not_participated ), ' value="1" /><label for="not-participated" title="Vis kun de som ikke har deltatt tidligere.">Kun de ikke deltatt tidligere &nbsp; </label>';
		echo PHP_EOL, '  <input id="pending-only" type="checkbox" name="pending-only"', checked( $pending_only ), ' value="1" /><label for="pending-only" title="Vis kun kommende/ventende (neste sesong), upubliserte kurs.">Kun ventende (neste sesong) &nbsp; </label>';
		echo PHP_EOL, '  <input id="fresh-only" type="checkbox" name="fresh-only"', checked( $is_fresh ), ' value="', ! $is_fresh, '" /><label for="fresh-only" title="Hent oppaderte (ferske) data.">Ferske data, takk &nbsp;</label>';
		echo PHP_EOL, '  <input type="hidden" name="page" value="', esc_attr( $_GET['page'] ), '" />';
		echo PHP_EOL, '  <button class="button button-primary" style="position: relative; bottom: .5em;" type="submit" title=Kjør!">Vis</button>';
		echo PHP_EOL, ' </form>';
		echo PHP_EOL, ' <p><br /></p>';
		$evt = 0;
		$nis = 0;
		$npb = 0;
		$qty = 0;
		$max = 0;
		$par = 0;
		$der = 0;
		echo PHP_EOL, ' <table class="kontrolliste widefat" style="width: 101%"', WP_DEBUG ? ' border="1"' : '', '>';
		echo PHP_EOL, '  <style scoped="scoped">';
		echo PHP_EOL, '   .kontrolliste tr td, .kontrolliste tr th { padding: 2px .3em; font-size: larger; }';
		echo PHP_EOL, '   .kontrolliste thead tr th { font-weight: bold; font-size: large; background-color: rgb(0, 160, 210); color: white; }';	// rgb(107,196,255)
		echo PHP_EOL, '   .kontrolliste thead tr th:first-child { border-radius: .5em 0 0 0; }';
		echo PHP_EOL, '   .kontrolliste thead tr th:last-child { border-radius: 0 .5em 0 0; }';
		echo PHP_EOL, '   .kontrolliste tbody tr th, .kontrolliste tfoot tr th { vertical-align: top; }';
		echo PHP_EOL, '   .kontrolliste caption span { background-color: rgb(0, 160, 210); color: white; padding: 0 2px; border: solid 4px rgb(0, 160, 210); border-bottom: none; border-radius: .5em .5em 0 0; }';
		echo PHP_EOL, '   .kontrolliste a       { text-decoration: none; color: inherit; }';
		echo PHP_EOL, '   .kontrolliste a:hover { text-decoration: underline; }';
		echo PHP_EOL, '   .kontrolliste tbody tr:not(:first-of-type) td, .kontrolliste tbody tr:not(:first-of-type) th[scope="row"] { border-top: dotted 1px lightgray; }';
		echo PHP_EOL, '   .kontrolliste tfoot tr th { font-weight: bold; font-size: large; }';
		echo PHP_EOL, '  </style>';
		echo PHP_EOL, '  <caption title="', $is_fresh ? 'Friske data' : 'Transient: ' . $transient_name , '. Tidsutløp: ', number_format( $transient_time / MINUTE_IN_SECONDS, 0, ',', '' ), ' min."><span>Deltakere ', $not_participated ? 'som ikke har deltatt tidligere' : '', ' på ', $outofstock_only ? 'fulltegnede' : 'alle', ' ', $pending_only ? 'kommende' : 'publiserte', ' kurs', $instr ? ' med instruktør ' . get_user_by( 'id', $instr )->display_name : '', $is_fresh ? ', ferske data hentet' : '', '</span></caption>';
		if ( count( $events ) ) {
			echo PHP_EOL, '  <thead>';
			echo PHP_EOL, '  <tr>';
			echo PHP_EOL, '   <th style="text-align: left;  width: 9%;" title="Lenker til å vise kurs.">Kurs</th>';
			echo PHP_EOL, '   <th style="text-align: right; width: 7%;" title="Lenker til å redigere kurs.">Tid</th>';
			echo PHP_EOL, '   <th style="text-align: right; width: 2%;" title="Lenker til deltakeroversikt.">Påmeldt</th>';
			if ( ! $outofstock_only ) {
				echo PHP_EOL, '   <th style="text-align: left; width: 2%;" title="Lenker til å redigere kurs.">Maks</th>';
				echo PHP_EOL, '   <th style="text-align: left; width: 3%;">Fullt</th>';
			}
			echo PHP_EOL, '   <th style="text-align: left; width: 76%" colspan="8">', get_admin_page_title(), '</th>';
			echo PHP_EOL, '  </tr>';
			echo PHP_EOL, '  </thead>';
			echo PHP_EOL, '  <tbody>';
			$pre_locati = false;
			foreach( $events as $event ) {
				$event_id = $event->get_id();
				$start_dt = Kursoversikt::get_event_start_time( $event_id );
				$orders_transient_name = md5( Kursoversikt::pf . $event_id . '_'. serialize( [ 'completed' ] ) . '_ASC' );
				$order_ids = get_transient( $orders_transient_name );
				$orders_is_fresh = $fresh_only || ! is_array( $order_ids ) || ! count( $order_ids );
				if ( $orders_is_fresh ) {
					$order_ids = self::get_order_ids_by_product( $event_id, [ 'completed' ], 'ASC' );
					set_transient( $orders_transient_name, $order_ids, $transient_time );
				}
				$event_quant = 0;
				$field_values = [];
				$participants = [];
				foreach ( $order_ids as $order_id ) {
					$order = wc_get_order( $order_id );
					$order_items = $order->get_items();
					foreach ( $order_items as $order_item_id => $order_item ) {
						if ( $order_item->get_product_id() == $event_id ) {
							$item_quant = $order_item->get_quantity();
							if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) {
								$item_quant += $order->get_qty_refunded_for_item( $order_item_id );
							}
							$meta_data = $order_item->get_meta( 'participant', false );
							foreach ( $meta_data as $meta_id => $meta_item ) {
								$meta_item_data = $meta_item->get_data();
								if ( $meta_item_data['key'] == 'participant' ) {
									if ( ! $not_participated || ! self::new_participated_before( $order, $meta_item_data['value'], $is_fresh ) ) {
										if ( ! strtotime( explode( ' ', $meta_item_data['value'], 2 )[0] ) ) {
											$meta_item_data['value'] = '&nbsp;&nbsp; ' . $meta_item_data['value'];
											$der++;
										}
										$participants[ $order_id ][ $meta_id ] = strip_tags( $meta_item_data['value'] );
									}
								}
							}
							$event_quant += $item_quant;
						}
					}
				}
				$locati = get_the_terms( $event_id, Kursoversikt::$woo_loc_tax )[0]->name;
				$loc_id = get_the_terms( $event_id, Kursoversikt::$woo_loc_tax )[0]->term_id;
				if ( $pre_locati != $locati ) {
					echo PHP_EOL, '<tr><td colspan="', 11 + ( 2 * ! $outofstock_only ), '"><hr /></td></tr>';
					echo PHP_EOL, '  <tr><th colspan="2" scope="col" style="font-weight: bold; font-size: large; text-align: right;">', $locati, '</th><th colspan="', 9 + ( 2 * ! $outofstock_only ), '"></th></tr>';
				}
				echo PHP_EOL, '  <tr title="', $locati, ' ', date_i18n( 'j. F Y', $start_dt ), '. Ordre er ', $orders_is_fresh ? '' : 'ikke ', 'ferske.">';
				echo PHP_EOL, '   <th scope="row" rowspan="3" style="background: url(', get_the_post_thumbnail_url( $event->get_id(), 'small' ), ') no-repeat 100% 1.2em; background-size: 25px 25px;"><a href="', get_the_permalink( $event_id ), '" title="Se presentasjon på front, kjøp/meld på.">', strip_tags( wc_get_product_category_list( $event_id ) ), '</a></td>';
				echo PHP_EOL, '   <td rowspan="3" style="text-align: right;"><a href="', get_edit_post_link( $event_id ), '" title="Instruktør: ', get_user_by( 'id', intval( get_post_meta( $event->get_id(), Kursoversikt::pf . 'instructor', true ) ) )->display_name, '. Rediger kurs.">', date_i18n( 'l&#128336;&\n\b\s\p;H:i', $start_dt ), '</a></td>';
				$maximum = $event->get_stock_quantity() + $event_quant;
				echo PHP_EOL, '   <td rowspan="3" style="text-align: right; font-weight: bold;"><span style="', $event_quant < 3 ? 'background-color: khaki;' : ( $event_quant > $maximum ? 'background-color: hotpink;' : '' ), '">', self::get_participant_link( '&nbsp; ' . $event_quant . ' &nbsp;', $event_id ), '</span></td>';	
				$par += $event_quant;
				if ( ! $outofstock_only ) {
					echo PHP_EOL, '   <td rowspan="3" style="text-align: right; font-weight: bold;" title="Ledige ', $event->get_stock_quantity(), '."><a href="', get_edit_post_link( $event_id ), '" style="padding: 0 .5em;', $maximum < 8 || $maximum > 9 ? 'background-color: khaki;' : '', '" title="Rediger kurs.">', $maximum, '</a></td>';
					$qty += $event->get_stock_quantity();
					$max += $maximum;
					echo PHP_EOL, '   <td rowspan="3" style="text-align: center;" title="Ledige: ', $event->get_stock_quantity(), '."> &nbsp; ', $event->get_stock_quantity() ? '&#129297;' : ' &#10004;', '</td>';
					$nis += ( $event->get_stock_quantity() ? 0 : 1 );
				}
				$col = 0;
				foreach ( $participants as $order_id => $participantz ) {
					$order = wc_get_order( $order_id );
					$created   = method_exists( $order, 'get_date_created'   ) ? strtotime( $order->get_date_created()   ) : '';
//					$completed = method_exists( $order, 'get_date_completed' ) ? strtotime( $order->get_date_completed() ) : '';
					$billing_n = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ', tlf ' . $order->get_billing_phone();
					foreach ( $participantz as $participant ) {
						$col++;
						$block = '<span style="background-color: hotpink; display: inline-block; width: 3.7em;">&nbsp;</span>';
						$participant = explode( ' ', $participant, 2 );
						$participant = count( $participant ) > 1 ? ( strtotime( $participant[0] ) ? date_i18n( 'd.m.y', strtotime( $participant[0] ) ) : $block ) . ' ' . $participant[1] : $block . ' ' . $participant[0];
						echo PHP_EOL, '   <td title="Påmeldt ', wp_date( 'd.m.Y H:i', $created ), ' av ', $billing_n, '. Rediger ordre." style="width: 19%;"><a  href="', get_edit_post_link( $order_id ), '">', $participant, '</a></td>';
						if ( $col % 4 == 0 /*&& $col != count( $participantz ) * count( $participants ) - 1*/ ) {
							echo '</tr>';
							echo PHP_EOL, '   <tr><!--td colspan="', 3 + ( 2 * ! $outofstock_only ), '"></td-->';
						}
					}
					$npb++;
				}
				echo PHP_EOL, '   <tr></tr><tr>';
				$pre_locati = $locati;
				$evt++;
			}
			echo PHP_EOL, '  </tbody>';
			echo PHP_EOL, '  <tfoot>';
			echo PHP_EOL, '   <tr>';
			echo PHP_EOL, '    <td colspan="', 9 + ( 2 * ! $outofstock_only ), '"><hr /></td>';
			echo PHP_EOL, '   </tr>';
			echo PHP_EOL, '   <tr>';
			echo PHP_EOL, '    <th scope="row">Sum:</th><td style="text-align: right;" title="Antall kurs.">', $evt, '</td>';
			echo PHP_EOL, '    <td style="text-align: right; font-weight: bold;" title="Påmeldte.">', $par, ' &nbsp; </td>';
			if ( ! $outofstock_only ) {
				echo PHP_EOL, '    <td style="text-align: right; font-weight: bold;" title="Maksimalt antall. Ledige ', $qty, '.">', $max, '</td>';
				echo PHP_EOL, '    <td style="text-align: center;" title="Fulltgnede.">', $nis, '</td>';
			}
			echo PHP_EOL, '    <td style="text-align: left;" title="', get_admin_page_title(), '."> &nbsp; ', $npb, '</td>';
			echo PHP_EOL, '    <td colspan="', 3 + ( 2 * ! $outofstock_only ), '" style="text-align: left;" title="Født dato mangler.">', $der ? '<span style="background-color: hotpink;"> &nbsp; ' . $der . ' &nbsp;  </span>' : $der,'</td>';
			echo PHP_EOL, '   </tr>';
		} else {
			echo PHP_EOL, '   <tr>';
			echo PHP_EOL, '    <td colspan="', 3 + ( 2 * ! $outofstock_only ), '">Ingen ', $outofstock_only ? 'fulltegnede' : '', ' ', $pending_only ? 'kommende' : 'publiserte', ' kurs funnet.</td>';
			echo PHP_EOL, '   <tr>';
		}
		echo PHP_EOL, '  </tfoot>';
		echo PHP_EOL, ' </table>';
		echo PHP_EOL, '</div>';
//		include_once 'convert.php';
		Kursoversikt::footer();
	}
	
	public static function find_prevous_participant( object $current_order, int $event_id, int $participant_id, string $arg, $value ): bool {
		$before = false;
		if ( $current_order->get_date_completed() ) {
			$current_values = [];
			$field_first_names = array_keys( self::WC_ESC_FIELDS );
			foreach ( $field_first_names as $field_first_name ) {
				$current_values[] = explode( ' ', trim( get_post_meta( $current_order->get_id(), $event_id . '_' . $field_first_name . '_' . $participant_id, true ) ) )[0];
			}
			$order_ids = wc_get_orders( [
				'type'           => 'shop_order',
				'status'         => 'completed',
//				'date_completed' => '<' . $current_order->get_date_completed()->getTimestamp(),
				'date_completed' => '0...' . $current_order->get_date_completed()->getTimestamp(),
				'exclude'        => [ $current_order->get_id() ],
				'return'         => 'ids',
				$arg             => $value,
			] );
			foreach ( $order_ids as $order_id ) {
				$values = get_post_meta( $order_id );
				$found = 0;
				$prev_values = [];
				foreach ( $values as $key => $value ) {
					if ( count( $value ) == 1 && ( strpos( $key, '_' . $field_first_names[0] . '_', 1 ) || strpos( $key, '_' . $field_first_names[1] . '_', 1 ) ) ) {
						$prev_values[] = mb_strtolower( trim( $value[0] ) );
					}
				}
				foreach ( $current_values as $current_value ) {
					$len = mb_strlen( $current_value );
					$found += in_array( mb_strtolower( $current_value ), array_map( function( $value ) use ( $len) {
						return mb_substr( $value, 0, $len );
					}, $prev_values ) ) ? 1 : 0;
				}
				$before = $before || $found == count( $current_values );
			}
		} else {
			error_log( __CLASS__ . '::' . __FUNCTION__ . ' ' . get_class( $current_order ) . ' ' . $current_order->get_id() . ' ' . var_export( $current_order->get_date_completed(), true ) . ' in ' . __FILE__ . ':' . __LINE__ );
		}
		return $before;
	}

	public static function new_find_prevous_participant( object $current_order, string $participant, string $arg, $value ): bool {
		$before = false;
		if ( $current_order->get_date_completed() ) {
			$order_ids = wc_get_orders( [
				'type'           => 'shop_order',
				'status'         => 'completed',
//				'date_completed' => '<' . $current_order->get_date_completed()->getTimestamp(),
				'date_completed' => '0...' . $current_order->get_date_completed()->getTimestamp(),
				'exclude'        => [ $current_order->get_id() ],
				'return'         => 'ids',
				$arg             => $value,
			] );
			foreach ( $order_ids as $order_id ) {
				$prev_participants = [];
				$order = wc_get_order( $order_id );
				$order_items = $order->get_items();
				foreach ( $order_items as $order_item ) {
//					$meta = $order_item->get_meta( 'participant' );
					$prev_participants[] = mb_strtolower( trim( $order_item->get_meta( 'participant' ) ) );
				}
//				var_dump( mb_strtolower( $participant ), $prev_participants );
				$len = mb_strlen( $participant );
				$found = in_array( mb_strtolower( $participant ), array_map( function( $value ) use ( $len) {
					return mb_substr( $value, 0, $len );
				}, $prev_participants ) );
				$before = $before || $found;
			}
		} else {
			error_log( __CLASS__ . '::' . __FUNCTION__ . ' ' . get_class( $current_order ) . ' ' . $current_order->get_id() . ' ' . var_export( $current_order->get_date_completed(), true ) . ' in ' . __FILE__ . ':' . __LINE__ );
		}
		return $before;
	}

	public static function participated_before( object $order, int $event_id, int $participant_id ): bool {
		$transient_name = Kursoversikt::pf . 'rc_' . $order->get_id() . '_' . $event_id . '_' . $participant_id;
		$transient_time = WP_DEBUG ? 2 * MINUTE_IN_SECONDS : Kursoversikt::transient_time * DAY_IN_SECONDS;
		$befores = get_transient( $transient_name );
		if ( is_array( $befores ) ) {
//			echo PHP_EOL, '<!-- ', $transient_name, ' | ', $transient_time / HOUR_IN_SECONDS, ' hours | ', print_r( $befores, true ), ' -->';
		} else {
			$before = false;
			$args = [
				'customer_id'         => $order->get_customer_id(),
				'customer'            => $order->get_billing_email(),
				'billing_phone'       => substr( $order->get_billing_phone(), -8 ),
				'customer_ip_address' => $order->get_customer_ip_address(),
				'billing_address_1'   => $order->get_billing_address_1(),
			];
			foreach ( $args as $arg => $value ) {
				if ( $value ) {
					$before = self::find_prevous_participant( $order, $event_id, $participant_id, $arg, $value );
					if ( $before ) break;
				}
			}
			$befores = [ $arg => $before ];
			set_transient( $transient_name, $befores, $transient_time );
		}
		return $befores[ array_key_first( $befores ) ] != 0;
	}

	public static function new_participated_before( object $order, string $participant, bool $fresh = false ): bool {
		$transient_name = Kursoversikt::pf . 'rc_' . $order->get_id() . '_' . $participant;
		$transient_time = WP_DEBUG ? MINUTE_IN_SECONDS : Kursoversikt::transient_time * DAY_IN_SECONDS;
		$befores = get_transient( $transient_name );
		$is_fresh = $fresh || ! is_array( $befores );
		if ( $is_fresh ) {
			$before = false;
			$args = [
				'customer_id'         => $order->get_customer_id(),
				'customer'            => $order->get_billing_email(),
				'billing_phone'       => substr( $order->get_billing_phone(), -8 ),
				'customer_ip_address' => $order->get_customer_ip_address(),
				'billing_address_1'   => $order->get_billing_address_1(),
			];
			foreach ( $args as $arg => $value ) {
				if ( $value ) {
					$before = self::new_find_prevous_participant( $order, $participant, $arg, $value );
					if ( $before ) break;
				}
			}
			$befores = [ $arg => $before ];
			set_transient( $transient_name, $befores, $transient_time );
		}
		return $befores[ array_key_first( $befores ) ] != 0;
	}

	public static function age( string $participant, WC_Order_Item_Product $order_item ): string {
		$born = strtotime( explode( ' ', $participant, 2 )[0] );
		$event_id = $order_item->get_product_id();
		$start = Kursoversikt::get_event_start_time( $event_id );
		$age_months = ( $start - $born ) / MONTH_IN_SECONDS;
		$age_years  = ( $start - $born ) / YEAR_IN_SECONDS;
		return $age_months < 24. ? number_format( $age_months, 1, ',', '' ) . ' mnd' : number_format( $age_years, 1, ',', '' ) . ' år';
	}
	
	public static function age_diff( string $participant, WC_Order_Item_Product $order_item ): string {
		$born = strtotime( explode( ' ', $participant, 2 )[0] );
		$event_id = $order_item->get_product_id();
		$start = Kursoversikt::get_event_start_time( $event_id );
		$end = $start + ( ( Kursoversikt::$event_times + 1 ) * WEEK_IN_SECONDS );
		$start_age_mnd = ( $start - $born ) / MONTH_IN_SECONDS;
		$end_age_mnd = ( $end   - $born ) / MONTH_IN_SECONDS;
		$group_slug = get_the_terms( $event_id, Kursoversikt::$woo_event_tax )[0]->slug;
		$ages = array_map( 'floatval', explode( '-', $group_slug, 2 ) );
		return $start_age_mnd >= $ages[1] + 1. ? '&plus;' : ( $end_age_mnd <= $ages[0] ? '&ndash;' : '' );
	}

	public static function in_age( string $participant, WC_Order_Item_Product $order_item ): bool {
		$born = strtotime( explode( ' ', $participant, 2 )[0] );
		$event_id = $order_item->get_product_id();
		$start = Kursoversikt::get_event_start_time( $event_id );
		$end = $start + ( ( Kursoversikt::$event_times + 1 ) * WEEK_IN_SECONDS );
		$start_age_mnd = ( $start - $born ) / MONTH_IN_SECONDS;
		$end_age_mnd = ( $end   - $born ) / MONTH_IN_SECONDS;
		$group_slug = get_the_terms( $event_id, Kursoversikt::$woo_event_tax )[0]->slug;
		$ages = array_map( 'floatval', explode( '-', $group_slug, 2 ) );
		return $start_age_mnd <= $ages[1] + 1. && $end_age_mnd >= $ages[0];
	}

	public static function get_participant_link( string $anchor, $order_item_or_product_id ): string {
		if ( is_a( $order_item_or_product_id, 'WC_Order_Item_Product' ) ) {
			$event_id = $order_item_or_product_id->get_product_id();
		} else {
			$event_id = $order_item_or_product_id;
		}
		$loc_id = get_the_terms( $event_id, Kursoversikt::$woo_loc_tax )[0]->term_id;
		$link = htmlspecialchars( add_query_arg( [ 'page' => 'deltakere', 'event' => $event_id ], admin_url( '.' ) ) . '#printarea-' . $loc_id );
		return '<a href="' . $link . '" title="Se instruktørliste.">' . esc_attr( $anchor ) . '</a>';
	}
	
	public function woo_render() {
		global $wp, $weekday;
		if ( ! current_user_can( 'read_participants' ) ) {
			return;
		}
		$title = get_admin_page_title();
?>
	<script type="text/javascript">
		/*--This JavaScript method for Print command--*/
		function PrintDoc( locName ) {
			var toPrint = document.getElementById('printarea-' + locName );
			var popupWin = window.open('', '_blank', 'width=1200,height=600,location=no,left=100px');
			popupWin.document.open();
			popupWin.document.write('<!doctype html><html><title>::Forhåndsvisning::</title><link rel="stylesheet" type="text/css" href="<?=plugins_url("admin-print.css",__FILE__)?>" media="all" /></head><body onload="window.print()">')
			popupWin.document.write(toPrint.innerHTML);
			popupWin.document.write('</body></html>');
			popupWin.document.close();
		}
		/*--This JavaScript method for Print Preview command--*/
		function PrintPreview( locName ) {
			var toPrint = document.getElementById('printarea-' + locName );
			var popupWin = window.open('', '_blank', 'width=1200,height=600,location=no,left=100px');
			popupWin.document.open();
			popupWin.document.writeln('<!doctype html>');
			popupWin.document.write('<html><title>::Forhåndsvisning::</title><link rel="stylesheet" type="text/css" href="<?=plugins_url("admin-print.css",__FILE__)?>" media="screen" /></head><body>')
			popupWin.document.write(toPrint.innerHTML);
			popupWin.document.write('</body></html>');
			popupWin.document.close();
		}

		/* tabs */
		function openTab(evt, locName) {
			var i, tabpanel, tab;

			// Get all elements with class="tabpanel" and hide them
			tabpanel = document.getElementsByClassName("tabpanel");
			for (i = 0; i < tabpanel.length; i++) {
				tabpanel[i].setAttribute("hidden", "hidden" );
			}

			// Get all elements with class="tab", remove the class "active", set aria-selected to false and tabindex to -1
			tab = document.getElementsByClassName("tab");
			for (i = 0; i < tab.length; i++) {
				tab[i].className = tab[i].className.replace(" active", "");
				tab[i].setAttribute("aria-selected", "false");
				tab[i].setAttribute("tabindex", "-1");
			}

			// Show the current tab, add an "active" class to the button that opened the tab, set aria-selected to true and remove tabindex
			document.getElementById(locName).removeAttribute( "hidden" );
			evt.currentTarget.className += " active";
			evt.currentTarget.setAttribute("aria-selected", "true");
			evt.currentTarget.removeAttribute("tabindex");
		}

	</script>
	<div id="loader" style="display: none;"></div>
	<div id="load-text" style="display: none;">Et øyeblikk&hellip;</div>
	<div id="content" class="wrap">
		<h2><?=$title?></h2>
		<h3>Deltakerliste kan også velges direkte fra <a href="edit.php?post_type=product"><?=get_post_type_labels(get_post_type_object('product'))->menu_name?></a>, kolonne &laquo;<abbr title="Instruktør">In</abbr>&raquo;, fra <a href="edit.php?post_type=shop_order">Ordre</a> detaljer - Deltakere og fra <a href="/kursoversikt/?participants=1">Kursoversikt</a> (<a href="/?page_id=2841&amp;participants=1">neste sesong</a>).</h3>
		<div role="tablist" aria-label="<?=get_admin_page_title()?>">
<?php
		$is_post = is_admin() && current_user_can( 'read_participants' ) && $_SERVER['REQUEST_METHOD'] === 'POST';
		$locations = get_terms( [ 'taxonomy' => Kursoversikt::$woo_loc_tax, 'orderby' => 'term_id', 'hide_empty' => false ] );
		$wd_range = range( 1, 5 );
		$iloc = 0;
		foreach ( $locations as $location ) {
			$loc_id = intval( $location->term_id );
			$curr_loc = isset( $_POST['loc'] ) ? $loc_id != intval( $_POST['loc'] ) : $iloc;
			$loc = $location->name;
?>
			<button id="label-<?=sanitize_key($loc)?>" role="tab"<?=$loc?' tabindex="-1"':''?> aria-selected="<?=$curr_loc?'false':'true'?>" class="tab<?=$curr_loc?'': ' active'?>" onclick="openTab(event, '<?=sanitize_key($loc)?>');"><?=$loc?></button>
<?php
			$iloc++;
		}
?>
		</div><br style="clear: left;"/>
<?php
		$iloc = 0;
		foreach ( $locations as $location ) {
			$loc_id = intval( $location->term_id );
			$curr_loc = isset( $_POST['loc'] ) ? $loc_id != intval( $_POST['loc'] ) : $iloc;
			$loc = $location->name;

?>
		<div id="<?=sanitize_key($loc)?>" role="tabpanel" class="tabpanel"<?=$curr_loc?' hidden="hidden"':''?>>
			<div id="search">
<?php
			$start = Kursoversikt::get_events_from();
			$selected_cat   = $is_post && isset( $_POST['cat'  ] ) ?   intval( $_POST['cat'  ] ) : false;
			$selected_day   = $is_post && isset( $_POST['day'  ] ) ?   intval( $_POST['day'  ] ) : false;
			$selected_time  = $is_post && isset( $_POST['time' ] ) ? floatval( $_POST['time' ] ) : false;
			$selected_inst  = $is_post && isset( $_POST['inst' ] ) ?   intval( $_POST['inst' ] ) : false;
			$selected_event = $is_post && isset( $_POST['event'] ) ?   intval( $_POST['event'] ) : false;

			$instructor     = wp_get_current_user();
			$instructor_id  = intval( $instructor->ID );
//			$selected_inst  = current_user_can( 'edit_shop_orders' ) && ( $selected_inst !== false || in_array( 'instructor', $instructor->roles ) ) ?
//				$selected_inst :
//				$instructor_id
			;
			$selected_inst  = current_user_can( 'edit_shop_orders' ) ? $selected_inst : get_current_user_id();

			$tax_query = [
				'cat' => [ 'taxonomy' => Kursoversikt::$woo_event_tax, 'field' => 'term_id', 'terms' => [ $selected_cat ? $selected_cat :  Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ] ] ],
				'loc' => [ 'taxonomy' => Kursoversikt::$woo_loc_tax,   'field' => 'term_id', 'terms' => $loc_id ],
//				'vis' => [ 'taxonomy' => 'product_visibility', 'field' => 'slug', 'terms'    => [ 'exclude-from-search', 'exclude-from-catalog' ], 'operator' => 'NOT IN' ],
			];
			$sql_start = date_i18n( 'Y-m-d', $start );
			$sql_time  = Kursoversikt::fmt_display_time( $selected_time );
			$meta_query = [ 'relation' => 'AND',
					[ 'relation' => 'AND',
						'start'      => [ 'key' => Kursoversikt::pf . 'start',      'value' => $sql_start, 'compare' => '>=', 'type' => 'DATE'    ], 
						'time'       => $selected_time ?
						                [ 'key' => Kursoversikt::pf . 'time',       'value' => $sql_time,  'compare' =>  '='                      ] :
						                false,
					],
				$selected_inst ? [
					[ 'relation' => 'OR',
						'instructor' => [ 'key' => Kursoversikt::pf . 'instructor', 'value' => $selected_inst,          'compare' =>  '=', 'type' => 'NUMERIC' ],
						'instr_zero' => [ 'key' => Kursoversikt::pf . 'instructor', 'value' => 0,                       'compare' =>  '=', 'type' => 'NUMERIC' ],
					]
				] : false,
			];
			$events_query = new WP_Query( [
				'label'          => __CLASS__,
				'post_type'      => 'product',
				'post_status'    => [ 'publish', 'pending', 'future' ],
				'tax_query'      => $tax_query,
				'meta_query'     => $meta_query,
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'wdays'          => $selected_day ? array_merge( array_diff( array_keys( $weekday ), $wd_range ), [ $selected_day ] ) : false,
			] );
			$events = $events_query->posts;
			$events = Kursoversikt_Preorder::order_events( $events );

			echo PHP_EOL, '<form method="post" action="', admin_url( get_current_screen()->parent_file . '?page=' . esc_attr( $_GET['page'] ) ), '">';
			$categories = get_terms( [
				'taxonomy' => Kursoversikt::$woo_event_tax,
				'parent'   => Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ],
				'orderby' => 'slug',
				'hide_empty' => false,
			] );
			$cats = [];
			foreach( $categories as $category ) {
				$key = intval( explode( '-', $category->slug )[0] );
				$cats[ $key ] = $category;
			}
			ksort( $cats, SORT_NUMERIC );
			echo PHP_EOL, '<label for="cat">Filtre:</label> <select id="cat" name="cat" onchange="init(); this.form.submit();">';
			echo PHP_EOL, ' <option value=""', selected( false, $selected_cat, false ), '> -- alle aldersgrupper -- </option>';
			foreach ( $cats as $cat ) {
				$cat_id = intval( $cat->term_id );
				echo PHP_EOL, '<option value="', $cat_id, '"', selected( $cat_id, $selected_cat, false ), ' title="Alder: ', $cat->slug, ' mnd.">', $cat->name, '</option>';
			}
			echo PHP_EOL, '</select>';
			echo PHP_EOL, '<select name="day" onchange="init(); this.form.submit();">';
			echo PHP_EOL, ' <option value=""', selected( false, $selected_day, false ), '> -- alle ukedager -- </option>';
			foreach ( $wd_range as $wd ) {
				echo PHP_EOL, '<option value="', $wd, '"', selected( $wd, $selected_day, false ), '>', $weekday[ $wd ], '</option>';
			}
			echo PHP_EOL, '</select>';
			echo PHP_EOL, '<select name="time" onchange="init(); this.form.submit();">';
			echo PHP_EOL, ' <option value=""', selected( false, $selected_time, false ), '> - tid - </option>';
			for ( $start_time = 9.; $start_time <= 19.; $start_time += Kursoversikt::get_event_duration() ) {
				echo PHP_EOL, '<option value="', $start_time, '"', selected( $start_time, $selected_time, false ), '>', Kursoversikt::fmt_display_time( $start_time ), '</option>';
			}
			echo PHP_EOL, '</select>';
			$instructors   = get_users( [ 'role' => 'instructor' ] );
			echo PHP_EOL, '<select name="inst" onchange="init(); this.form.submit();" title="Antall: ', count( $instructors ),'.">';
			if ( current_user_can( 'read_private_pages' ) ) {
				echo PHP_EOL, ' <option value="0"', selected( false, $selected_inst, false ), '> -- alle instruktører -- </option>';
				foreach ( $instructors as $instructor ) {
					$instructor_id = intval( $instructor->ID );
					echo PHP_EOL, '<option value="', $instructor_id, '"', selected( $instructor_id, $selected_inst, false ), ' title="Instruktør-ID: ', $instructor_id, '.">', $instructor->display_name, '</option>';
				}
			} else {
					echo PHP_EOL, '<option value="', $instructor_id, '"', selected( $instructor_id, $selected_inst, false ), ' ' , disabled( true ), ' title="Instruktør-ID: ', $instructor_id, '.">', $instructor->display_name, '</option>';
			}
			echo PHP_EOL, '</select> &nbsp; <small>(kurs startet etter ', date_i18n( 'd.m.Y', $start ), ')</small>';
			echo PHP_EOL, '<noscript> &nbsp; <button type="submit" style="height: 2.2em; margin: .8px; padding: 2px; vertical-align: middle;">Filtrer listen nedenfor</button></noscript><br /><br />';
			echo PHP_EOL, '<input type="hidden" name="loc" value="' . intval( $loc_id ) . '" />';
			echo PHP_EOL, '</form>';
			$event_count = count( $events );
			if ( $event_count ) {
				echo PHP_EOL, '<form method="post" action="', admin_url( get_current_screen()->parent_file . '?page=' . esc_attr( $_GET['page'] ) ), '#printarea-', $loc_id, '">';
				echo PHP_EOL, '<label for="event">Kurs:</label> <select id="event" name="event" onchange="init(); this.form.submit();" title="Antall på ', $loc, ': ', $event_count, '.">';
				echo PHP_EOL, ' <option value=""', selected( false, $selected_day, false ), '> -- velg kurs -- </option>';
				foreach ( $events as $event ) {
					$event_id = intval( $event->ID );
					$date     = Kursoversikt::get_event_start_time( $event_id );
					$wday     = idate( 'w', $date );
					$inst     = intval( get_post_meta( $event_id, Kursoversikt::pf . 'instructor', true ) );
					$hour     = idate( 'H', $date );
					$min      = idate( 'i', $date );
					$time     = $hour + ( $min * MINUTE_IN_SECONDS / HOUR_IN_SECONDS );
					$key_time = strval( $time );
					$homi = strval( $hour + ( $min * MINUTE_IN_SECONDS / HOUR_IN_SECONDS  ) );	// Hour + minute as decimal
					$loc_id   = get_the_terms( $event_id, Kursoversikt::$woo_loc_tax )[0]->term_id;
					$order_ids = self::get_order_ids_by_product( $event_id, [ 'completed' ] );
					$event_quant = 0;
					foreach ( $order_ids as $order_id ) {
						$order = wc_get_order( $order_id );
						$order_items = $order->get_items();
						foreach ( $order_items as $order_item ) {
							if ( $order_item->get_product_id() == $event_id ) {
								$event_quant += $order_item->get_quantity();
							}
						}
					}
					$order_count = count( $order_ids );
					echo PHP_EOL, '<option value="', $event_id, '"', selected( $event_id, $selected_event, false ), disabled( $order_count, 0, false ), ' title="Kurs-ID: ', $event_id, ', Påmeldinger: ', $order_count, ', Instruktør-ID: ', $inst, ', ', $weekday[ $wday ], '.">', get_the_title( $event_id ), ' (', $event_quant, ')</option>';
				}
				echo PHP_EOL, '</select> <small>(', $event_count, ')</small>';
				echo PHP_EOL, '<input type="hidden" name="loc" value="' . intval( $loc_id ) . '" />';
				echo PHP_EOL, '<noscript> &nbsp; <button type="submit" style="height: 2.2em; margin: .8px; padding: 2px; vertical-align: middle;">Vis tabell</button></noscript>';
			} else {
				echo PHP_EOL, '<p>Ingen kurs funnet. Prøv endre sted eller filtrene.</p>';
			}
			echo PHP_EOL, '</form>', PHP_EOL;
?>
			<br/><br/>
			</div><!-- search -->
<?php
			if ( isset( $_GET['event'] ) || ( $is_post && ! empty( $_POST['event'] ) && ! empty( $_POST['loc'] ) && $_POST['loc'] == $location->term_id ) ) {
				$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
				$field_first_names = array_keys( $fields );
				$event_id = isset( $_POST['event'] ) ? intval( $_POST['event'] ) : intval( $_GET['event'] );
				$event = get_post( $event_id );
				$event_loc = get_the_terms( $event_id, Kursoversikt::$woo_loc_tax )[0]->name;
				$date = Kursoversikt::get_event_start_time( $event_id );
				$month = idate( 'm', $date );
				$weeks = 'uke ' . date_i18n( 'Y', $date ) . ' ' . date( 'W', $date ) . '-' . substr( '0' . date( 'W', strtotime( '+' . ( Kursoversikt::$event_times * Kursoversikt::$event_interval ) . ' days', $date ) ), -2, 2 );
				$meta = Kursoversikt_Settings::$event_meta;
				$meta_fields = array_keys( $meta );
				$event_title = get_the_title( $event );
				$year = idate( 'Y', $date );
				$age  = get_the_terms( $event_id, 'product_cat' )[0]->name;
				$wday = idate( 'w', $date );
				$float_time = idate( 'H', $date ) + ( idate( 'i', $date ) * MINUTE_IN_SECONDS / HOUR_IN_SECONDS  );
				$event_time = Kursoversikt::fmt_display_time( $float_time ) . '-' . Kursoversikt::fmt_display_time( $float_time + Kursoversikt::get_event_duration() );
				$event_day = $weekday[ $wday ];
//				$course = get_bloginfo() . ' ' . $event_season . ' ' . date_i18n( 'Y', $date );
				$course = get_bloginfo() . ' ' . $weeks;
				$timloc = ucfirst( $event_day ) . ' ' . date_i18n( 'j. F', $date ) . ' ' . $event_time . ' på ' . $event_loc;
				$instructor = get_user_by( 'id', get_post_meta( $event_id, Kursoversikt::pf . 'instructor', true ) );
				$instructor = $instructor ? $instructor->display_name : false;
				$loc_id = get_the_terms( $event_id, Kursoversikt::$woo_loc_tax )[0]->term_id;
?>
			<div id="printarea-<?=$loc_id?>" class="printarea">
				<hr/>
				<h1>Kurs: &nbsp; <?=$course?></h1>
				<h2>Aldersgruppe: &nbsp; <?=$age?></h2>
				<h3>Tid og sted: &nbsp; <?=$timloc?></h3>
				<h4><?=$meta[Kursoversikt::pf.'instructor']['label']?>: &nbsp; <?=$instructor?></h4>
				<hr/>
				<table class="widefat">
					<thead>
<?php
				echo '<tr>';
				echo '<th scope="col" style="width: 12%;">Notat</th>';
				foreach ( $fields as $field ) {
					echo '<th scope="col">', $field, '</th>';
				}
				echo '<th scope="col">Forelder</th>';
				echo '<th scope="col">Telefon</th>';
				echo '<th scope="col">DT?</th>';
				for ( $i = 1; $i <= Kursoversikt::$event_times; $i++ ) {
					echo '<th scope="col" class="square"><div> &nbsp; <br/> &nbsp; </div></th>';
				}
				echo '</tr>';
?>
					</thead>
					<tbody>
<?php
				$order_ids = self::get_order_ids_by_product( $event_id, [ 'completed' ], 'ASC' );
				foreach ( $order_ids as $order_id ) {
					$order = wc_get_order( $order_id );
					$order_items = $order->get_items();
					foreach ( $order_items as $order_item_id => $order_item ) {
						if ( $order_item->get_product_id() == $event_id ) {
							$quantity = $order_item->get_quantity();
							if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) {
								$refunded = $order->get_qty_refunded_for_item( $order_item_id );
								$quantity += $refunded;
							}
							$meta_data = $order_item->get_meta( 'participant', false );
//							var_dump( $meta_data );
							$participants = [];
							foreach ( $meta_data as $meta_key => $meta_item ) {
								$meta_item_data = $meta_item->get_data();
//								var_dump( $meta_key, $meta_item_data );
								if ( $meta_item_data['key'] == 'participant' ) {
									$values = explode( ' ', $meta_item_data['value'], 2 );
									$participants[ $meta_key ]['born'] = $values[0];
									$participants[ $meta_key ]['name'] = $values[1];
									$participants[ $meta_key ]['recu'] = self::new_participated_before( $order, $meta_item_data['value']  );
								}
							}
//							var_dump( $participants );
							for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
								$is_recurring = self::participated_before( $order, $event_id, $child_id ); 
								$new_is_recurring = $participants[$child_id]['recu'];
								echo '<tr title="Ordre #', $order_id, $refunded ? ', ref '. $refunded : '', '.">';
								echo '<td class="square"> &nbsp; <br/> &nbsp; </td>';
								foreach ( $field_first_names as $field_first_name ) {
									$field_name = $event_id . '_' . $field_first_name . '_' . $child_id;
									$field_value = esc_attr( get_post_meta( $order_id, $field_name, true ) );
									$field_value = $field_value && $field_first_name == 'fodt' ? ( substr( $field_value, 4, 1 ) == '-' ? date_i18n( 'd.m.y', strtotime( $field_value ) ) : false ) : $field_value;
									$field_value = $field_value ?: '<small style="font-size: small; font-weight: normal;">(mangler/feil)</small>';
//									echo '<td title="', $field_first_name == 'fodt' ? date_i18n( 'd.m.y', strtotime( $participants[$child_id]['born'] ) ) : $participants[$child_id]['name'], '">', $field_value, '</td>';
								}
								echo '<td>', $participants[$child_id]['name'], '</td>';
								echo '<td>', date_i18n( 'd.m.y', strtotime( $participants[$child_id]['born'] ) ), '</td>';
								echo '<td>', $order->get_billing_first_name(), ' ', $order->get_billing_last_name(), '</td>';
								echo '<td>', str_replace( '+47', '', str_replace( ' ', '', $order->get_billing_phone() ) ), '</td>';
								echo '<td>', $new_is_recurring ? '&#10004;' : '', '</th>';
								for ( $i = 1; $i <= Kursoversikt::$event_times; $i++ ) {
									echo '<td class="square"><div> &nbsp; <br/> &nbsp; </div></th>';
								}
								echo '</tr>';
							}
						}
					}
				}
?>
					</tbody>
				</table>
				<br/>
			</div><!-- printarea -->
			<div id="buttons">
				<p>
					<button onclick="window.print(); return false;">Velg skriver og skriv ut</button> &nbsp;
					<button onclick="PrintDoc( <?=$loc_id?> );">Skriv ut fra et sprettoppvindu</button> &nbsp;
					<button onclick="PrintPreview( <?=$loc_id?> );">Forhåndsvis i et sprettoppvindu</button> &nbsp;
			eller (<em>bedre</em>) trykk tast "Alt", velg så på nettlesermenyen (helt i toppen av vinduet) <strong>Fil &rarr; Forhåndsvis side</strong>.
				</p>
			</div><!-- buttons -->
<?php
			}
?>
		</div><!-- tabpanel -->
<?php
			$iloc++;
		}
		Kursoversikt::footer();
?>
	</div><!-- wrap -->
<?php
	}

	public function xtickera_render() {
		global $wp, $weekday;
?>
	<script type="text/javascript">
		/*--This JavaScript method for Print command--*/
		function PrintDoc( loc ) {
			var toPrint = document.getElementById('printarea');
			var popupWin = window.open('', '_blank', 'width=1200,height=600,location=no,left=100px');
			popupWin.document.open();
			popupWin.document.write('<!doctype html><html><title>::Forhåndsvisning::</title><link rel="stylesheet" type="text/css" href="<?=plugins_url("admin-print.css",__FILE__)?>" media="all"/></head><body onload="window.print()">')
			popupWin.document.write(toPrint.innerHTML);
			popupWin.document.write('</body></html>');
			popupWin.document.close();
		}
		/*--This JavaScript method for Print Preview command--*/
		function PrintPreview( loc ) {
			var toPrint = document.getElementById('printarea');
			var popupWin = window.open('', '_blank', 'width=1200,height=600,location=no,left=100px');
			popupWin.document.open();
			popupWin.document.write('<!doctype html><html><title>::Forhåndsvisning::</title><link rel="stylesheet" type="text/css" href="<?=plugins_url("admin-print.css",__FILE__)?>" media="screen"/></head><body>')
			popupWin.document.write(toPrint.innerHTML);
			popupWin.document.write('</body></html>');
			popupWin.document.close();
		}
	</script>
	<div class="wrap">
		<div id="search">
<?php
		$start = current_time( 'U' ) - ( Kursoversikt::WEEKS * ( WEEK_IN_SECONDS - 1 ) + ( 2 * DAY_IN_SECONDS ) );	// Start time not older than 8 weeks - 2 days
		$end   = current_time( 'U' ) - ( HOUR_IN_SECONDS / 2 );												// End time not passed in half an hour
		$meta_query = [
			[ 'key' => 'event_date_time',     'value' => $start, 'compare' => '>=', 'type' => 'DATETIME' ],
			[ 'key' => 'event_end_date_time', 'value' => $end,   'compare' => '>=', 'type' => 'DATETIME' ],
		];
		$event_query = new WP_Query( [
			'post_type'      => 'tc_events',
			'post_status'    => 'publish',
			'meta_key'       => 'event_location',
			'meta_query'     => $meta_query,
			'posts_per_page' => -1,
			'orderby'        => 'event_location',
			'order'          => 'DESC',
//			'fields'         => [ 'ID', 'post_title' ],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );
		$events = $event_query->posts;

		echo PHP_EOL, '<h2>Instruktørliste</h2>';
		echo PHP_EOL, '<form method="post" action="', admin_url( get_current_screen()->parent_file . '?page=' . esc_attr( $_GET['page'] ) ), '">';
		echo PHP_EOL, '<select name="ticket">';
		echo PHP_EOL, '<option value=""> -- velg kurs -- </option>';
		foreach ( $events as $event ) {
			$event_loc = get_post_meta( $event_id, 'event_location', true );
			$time = mysql2date( 'U', get_post_meta( $event->ID, 'event_date_time', true ) );
			$hour = idate( 'G', $time );
			$min  = idate( 'i', $time );
			$wday = idate( 'w', $time );
			$homi = strval( $hour + ( $min * MINUTE_IN_SECONDS / HOUR_IN_SECONDS  ) );
			$meta_query = [ [
				'key' => '_event_name',
				'value' => $event->ID,
				'compare' => '=',
				'type' => 'NUMERIC',
			] ];
			$ticket = new WP_Query( [
				'post_type' => 'product',
				'meta_query' => $meta_query,
				'posts_per_page' => 1,
				'no_found_rows' => true,
			] );
			$event->ticket = $ticket && is_array( $ticket->posts ) && count( $ticket->posts ) ? $ticket->posts[0] : null;
			$order_ids = $event->ticket ? self::get_order_ids_by_product( $event->ticket->ID ) : [];
			$event_quant = 0;
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				$order_items = $order->get_items();
				foreach ( $order_items as $order_item ) {
					if ( $order_item->get_product_id() == $event->ticket->ID ) {
						$event_quant += $order_item->get_quantity();
					}
				}
			}
			if ( count( $order_ids ) ) {
				echo PHP_EOL, '<option value="', $event->ticket->ID, '"', selected( $event->ticket->ID, intval( $_POST['ticket'] ), false ), ' title="Billett-ID: ', $event->ticket->ID, '.">', $event->ticket->post_title, ' ', $event_loc, ' ', $weekday[ $wday ], ' ', date_i18n( 'd. M', $time ), ' ', Kursoversikt::fmt_display_time( $homi ), ' (', $event_quant, ')</option>';
			}
		}
		echo PHP_EOL, '</select> &nbsp; <button type="submit" style="height: 2.2em; margin: .8px; padding: 2px; vertical-align: middle;">Hent og vis</button>';
		echo PHP_EOL, '</form>';
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['ticket'] ) ) {
			$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
			$field_first_names = array_keys( $fields );
			$ticket_id = intval( $_POST['ticket'] );
			$ticket = wc_get_product( $ticket_id );
			$event_id = intval( get_post_meta( $ticket_id, '_event_name', true ) );
			$event = get_post( $event_id );
			$event_title = explode( ' ', $event->post_title );
			$event_loc = get_post_meta( $event->ID, 'event_location', true );
			$event_season = mb_strtolower( $event_title[ count( $event_title ) - 1 ] );
			array_pop( $event_title );
			$event_title = implode( ' ', $event_title );
			$event_year = idate( 'Y', mysql2date( 'U', get_post_meta( $event_id, 'event_date_time', true ) ) );
			$event_age = $ticket->get_name();
			$time = mysql2date( 'U', get_post_meta( $event->ID, 'event_date_time', true ) );
			$hour = idate( 'G', $time );
			$min  = idate( 'i', $time );
			$wday = idate( 'w', $time );
			$homi = strval( $hour + ( $min * MINUTE_IN_SECONDS / HOUR_IN_SECONDS  ) );
			$event_time = Kursoversikt::fmt_display_time( $homi ) . '-' . Kursoversikt::fmt_display_time( $homi + .5 );
			$event_day = $weekday[ $wday ];
			$course = get_bloginfo() . ' ' . $event_season . ' ' . $event_year;
			$timloc = ucfirst( $event_day ) . ' ' . date_i18n( 'j. F', $time ) . ' kl ' . $event_time . ' på ' . $event_loc;
?>
			<br/><br/>
		</div>
		<div id="printarea">
			<hr/>
			<h1>Kurs: &nbsp; <?=$course?></h1>
			<h2>Aldersgruppe: &nbsp; <?=$event_age?></h2>
			<h3>Tid og sted: &nbsp; <?=$timloc?></h3>
			<hr/>
			<table class="widefat">
				<thead>
<?php
			echo '<tr>';
			echo '<th scope="col">Notat</th>';
			foreach ( $fields as $field ) {
				echo '<th scope="col">', $field, '</th>';
			}
			echo '<th scope="col">Forelder</th>';
			echo '<th scope="col">Telefon</th>';
			echo '<th scope="col">DT?</th>';
			for ( $i = 1; $i <= 10; $i++ ) {
				echo '<th scope="col" class="square"><div> &nbsp; <br/> &nbsp; </div></th>';
			}
			echo '</tr>';
?>
				</thead>
				<tbody>
<?php
			$order_ids = self::get_order_ids_by_product( $ticket_id );
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				$order_billing = get_post_meta( $order_id, 'billing_before', true );
				$order_items = $order->get_items();
				foreach ( $order_items as $order_item ) {
					if ( $order_item->get_product_id() == $ticket_id ) {
						$quantity = $order_item->get_quantity();
						for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
							echo '<tr title="Kurs-ID: ', $event_id, '.">';
							echo '<td class="square"><div> &nbsp; <br/> &nbsp; </td>';
							foreach ( $field_first_names as $field_first_name ) {
								$field_name = $ticket_id . '_' . $field_first_name . '_' . $child_id;
								$field_value = esc_attr( get_post_meta( $order_id, $field_name, true ) );
								$field_value = $field_value && $field_first_name == 'fodt' ? date_i18n( 'd.m.y', strtotime( $field_value ) ) : $field_value;
								$field_value = $field_value ?: '<small style="font-size: small; font-weight: normal;">(mangler)</small>';
								echo '<td title="Feltnavn: ', esc_attr( $field_name ), '.">', $field_value, '</td>';
							}
							echo '<td>', $order->get_billing_first_name(), ' ', $order->get_billing_last_name(), '</td>';
							echo '<td>', $order->get_billing_phone(), '</td>';
							echo '<td>', $order_billing, '</th>';
							for ( $i = 1; $i <= 10; $i++ ) {
								echo '<td class="square"><div> &nbsp; <br/> &nbsp; </div></th>';
							}
							echo '</tr>';
						}
					}
				}
			}
?>
				</tbody>
			</table>
			<br/>
		</div>
		<div id="buttons">
			<p>
			<button onclick="window.print(); return false;">Velg skriver og skriv ut</button> &nbsp;
			<button onclick="PrintDoc();">Skriv ut fra et sprettoppvindu</button> &nbsp;
			<button onclick="PrintPreview();">Forhåndsvis i et sprettoppvindu</button> &nbsp;
			eller (<em>bedre</em>) trykk tast "Alt", velg så på nettlesermenyen (helt i toppen av vinduet) <strong>Fil &rarr; Forhåndsvis side</strong>.
			</p>
		</div>
	</div>
<?php
		}
	}

	public function admin_order_item_headers( $order ) {
		echo '<th style="text-align: left;">Deltaker(e)</th>';
	}
	
	public function admin_order_item_values( $product, $item ) {
		$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
		$field_first_names = array_keys( $fields );

		if ( method_exists( $item, 'get_product_id' ) ) {
			$product_id = $item->get_product_id();
			$order_id   = $item->get_order_id();
			$item_quant = $item->get_quantity();
			$title   = '';
			$display = '';
			$hellip  = '';
			$meta_values = '';
			$field_value_count = 0;
			for ( $child_id = 1; $child_id <= $item_quant; $child_id++ ) {
				foreach ( $field_first_names as $field_first_name ) {
					$field_name = $product_id . '_' . $field_first_name . '_' . $child_id;
					$field_value = get_post_meta( $order_id, $field_name, true );
					if ( $field_value ) {
						$field_value = $field_first_name == 'fodt' ? date_i18n( 'd.m.y', strtotime( $field_value ) ) : $field_value;
						$field_value_count++;
						$title   .= $fields[ $field_first_name ] . ': ' . $field_value . ', ';
						$display .= $child_id <= 2 && $field_first_name == 'navn' ? $field_value . ', ' : '';
						$hellip  .= $child_id >  2 ? '&hellip;' : '';
					}
				}
			}
			$title   = rtrim( $title,   ', ' );
			$display = rtrim( $display, ', ' ) . $hellip;
			$participants_count = ceil( $field_value_count / count( $field_first_names ) );
			if ( $participants_count ) {
				if ( $item->get_order()->get_status() == 'completed' ) {
					$loc_id = get_the_terms( $product_id, Kursoversikt::$woo_loc_tax )[0]->term_id;
					echo '<td title="', esc_attr( $title ), '."><a href="index.php?page=deltakere&event=', $product_id, '#printarea-', $loc_id ,'">', $display, '</a></td>';
				} else {
					echo '<td title="', esc_attr( $title ), '.">', $display, '</td>';
				}
			} else {
				echo '<td title="(', $participants_count,')."> &nbsp; &nbsp; </td>';
			}
		}
	}

	public function order_item_display_meta_value( $value, $meta, $order_item ) {
		if ( is_admin() && trim( strip_tags( $value ) ) && ! in_array( $order_item->get_order()->get_status(), [ 'cancelled', 'failed', 'trah' ] ) ) {
			if ( $meta->__get( 'key' ) == 'participant' && method_exists( $order_item, 'get_product_id' ) ) {
//				$product_id = $item->get_product_id();
//				$loc_id = get_the_terms( $product_id, Kursoversikt::$woo_loc_tax )[0]->term_id;
//				$value = '<a href="index.php?page=deltakere&event=' . $product_id . '#printarea-' . $loc_id . '">' . $value . '</a>';
				$in_age = Kursoversikt_Deltakere::in_age( $order_item->get_meta( 'participant' ), $order_item );
				$age = Kursoversikt_Deltakere::age( $order_item->get_meta( 'participant' ), $order_item );
				$age = $in_age ? $age : '<i style="color: red;">' . $age . '</i>';
				$value = ( $order_item->get_order()->get_status() == 'completed' ? Kursoversikt_Deltakere::get_participant_link( $value, $order_item ) : $value ) . ' (' . $age . ')';
			}
		}
		return $value;
	}

	public function xorder_item_add_action_buttons( $order ) {
		$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
		$field_first_name = array_key_first( $fields );
		$field_base_names = array_keys( $fields );
		$order_id = $order->get_id();
		$order_items = $order->get_items();
		$target_items_count = 0;
		$target_item = false;
		foreach ( $order_items as $order_item ) {
			$product_id = $order_item->get_product_id();
			$item_quant = $order_item->get_quantity();
			if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) {
				$item_quant += $order->get_qty_refunded_for_item( $order_item_id );
			}
			$participant_count = 0;
			for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
				$field_name = $product_id . '_' . $field_first_name . '_' . $participant_id;
				$participant_count += empty( get_post_meta( $order_id, $field_name, true ) ) ? 0 : 1;
			}
			if ( $participant_count == 0 ) {
				$target_item = $order_item;
				$target_items_count++;
			}	// else: Not zero participants, not a valid target, coninue to next item
		}
		$source_items_count = 0;
		if ( $target_items_count == 1 ) {
			foreach ( $order_items as $order_item ) {	// Source: Find item or items with same quantity as the one with no participants, to move from
				$item_quant = $order_item->get_quantity();
				if ( $target_item && $item_quant == $target_item->get_quantity() ) {
					$product_id = $order_item->get_product_id();
					$participant_count = 0;
					for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
						$field_name = $product_id . '_' . $field_first_name . '_' . $participant_id;
						$participant_count += empty( get_post_meta( $order_id, $field_name, true ) ) ? 0 : 1;
					}

					if ( $participant_count > 0 ) {
						$source_items_count++;
					}	// else: Not valid target, continue to next item
				}	// else: Not same quantity, continue to next item
			}
		}
		echo PHP_EOL, '<button type="button"', disabled( $source_items_count == 1, false , false ), ' onclick="document.post.submit();" class="button">Kopier deltakere</button>';
		echo $source_items_count == 1 ? '<input type="hidden" name="copy-participants" value="1" />' : '';
	}
	
	public function order_item_add_action_buttons( $order ) {
		$order_id = $order->get_id();
		$order_items = $order->get_items();
		$target_items_count = 0;
		$target_item = false;
		foreach ( $order_items as $order_item ) {
			$product_id = $order_item->get_product_id();
			$item_quant = $order_item->get_quantity();
			if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) {
				$item_quant += $order->get_qty_refunded_for_item( $order_item->get_id() );
			}
			$participant_count = 0;
			for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
				$item_meta_key = $item_quant == 1 ? 'Deltaker' : 'Tvilling ' . $participant_id;
				$participant_count += $order_item->meta_exists( $item_meta_key ) ? 1 : 0;
			}
			if ( $participant_count == 0 ) {
				$target_item = $order_item;
				$target_items_count++;
			}	// else: Not zero participants, not a valid target, coninue to next item
		}
		$source_items_count = 0;
		if ( $target_items_count == 1 ) {
			foreach ( $order_items as $order_item ) {	// Source: Find item or items with same quantity as the one with no participants, to move from
				$item_quant = $order_item->get_quantity();
				if ( $target_item && $item_quant == $target_item->get_quantity() ) {
					$product_id = $order_item->get_product_id();
					$participant_count = 0;
					for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
						$item_meta_key = $item_quant == 1 ? 'Deltaker' : 'Tvilling ' . $participant_id;
						$participant_count += $order_item->meta_exists( $item_meta_key ) ? 1 : 0;
					}

					if ( $participant_count > 0 ) {
						$source_items_count++;
					}	// else: Not valid target, continue to next item
				}	// else: Not same quantity, continue to next item
			}
		}
		echo PHP_EOL, '<button type="button"', disabled( $source_items_count == 1, false , false ), ' onclick="document.post.submit();" class="button">Kopier deltakere</button>';
		echo $source_items_count == 1 ? '<input type="hidden" name="copy-participants" value="1" />' : '';
	}
	
	public function copy_participants( $order_id ) {
		if ( is_admin() && isset( $_POST['copy-participants'] ) && $_POST['copy-participants'] ) {
			$order = wc_get_order( $order_id );
			$order_items = $order->get_items();
			$target_items_count = 0;
			$target_item = false;
			foreach ( $order_items as $order_item ) {	// Target: Find item or items with no participants, to copy to
				$product_id = $order_item->get_product_id();
				$item_quant = $order_item->get_quantity();
				if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) {
					$item_quant += $order->get_qty_refunded_for_item( $order_item_id );
				}
				$participant_count = 0;
				for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
					$item_meta_key = $item_quant == 1 ? 'Deltaker' : 'Tvilling ' . $participant_id;
					$meta_exists = $order_item->meta_exists( $item_meta_key );
					$item_meta = $meta_exists ? $order_item->get_meta( $item_meta_key ) : false;
					$participant_count += empty( $item_meta ) ? 0 : 1;
				}
				if ( $participant_count == 0 ) {
					$target_item = $order_item;
					$target_items_count++;
				}	// else: Not zero participants, not a valid target, coninue to next item
			}
			
			if ( $target_items_count == 1 ) {
				$source_items_count = 0;
				$source_item = false;
				foreach ( $order_items as $order_item ) {	// Source: Find item or items with same quantity as the one with no participants, to move from
					$item_quant = $order_item->get_quantity();
					if ( $target_item && $item_quant == $target_item->get_quantity() ) {
						$product_id = $order_item->get_product_id();
						$participant_count = 0;
						for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
							$meta_exists = $order_item->meta_exists( $item_meta_key );
							$item_meta = $meta_exists ? $order_item->get_meta( $item_meta_key ) : false;
							$participant_count += empty( $item_meta ) ? 0 : 1;
						}

						if ( $participant_count > 0 ) {
							$source_item = $order_item;
							$source_items_count++;
						}	// else: Not valid target, continue to next item
					}	// else: Not same quantity, continue to next item
				}

				if ( $source_items_count == 1 ) {
					$item_quant = max( $source_item->get_quantity(), $target_item->get_quantity() );	// Max, just to be safe
					for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
						$item_meta_key = $item_quant == 1 ? 'Deltaker' : 'Tvilling ' . $participant_id;
						$source_item_meta = $source_item->get_meta( $item_meta_key );
						$target_meta_exists = $target_item->meta_exists( $item_meta_key );
						if ( $target_meta_exists ) {
							$target_item->update_meta_data( $item_meta_key, $source_item_meta );
						} else {
							$target_item->add_meta_data( $item_meta_key, $source_item_meta, true );
						}
						$target_item->save_meta_data();
					}

				}
			}
		}
	}

	public function xcopy_participants( $order_id ) {
		if ( is_admin() && isset( $_POST['copy-participants'] ) && $_POST['copy-participants'] ) {
			$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
			$field_first_name = array_key_first( $fields );
			$field_base_names = array_keys( $fields );

			$order = wc_get_order( $order_id );
			$order_items = $order->get_items();
			
			$target_items_count = 0;
			$target_item = false;
			foreach ( $order_items as $order_item ) {	// Target: Find item or items with no participants, to copy to
				$product_id = $order_item->get_product_id();
				$item_quant = $order_item->get_quantity();
				$participant_count = 0;
				for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
					$field_name = $product_id . '_' . $field_first_name . '_' . $participant_id;
					$participant_count += empty( get_post_meta( $order_id, $field_name, true ) ) ? 0 : 1;
				}
				if ( $participant_count == 0 ) {
					$target_item = $order_item;
					$target_items_count++;
				}	// else: Not zero participants, not a valid target, coninue to next item
			}
			
			if ( $target_items_count == 1 ) {
				$source_items_count = 0;
				$source_item = false;
				foreach ( $order_items as $order_item ) {	// Source: Find item or items with same quantity as the one with no participants, to move from
					$item_quant = $order_item->get_quantity();
					if ( $target_item && $item_quant == $target_item->get_quantity() ) {
						$product_id = $order_item->get_product_id();
						$participant_count = 0;
						for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
							$field_name = $product_id . '_' . $field_first_name . '_' . $participant_id;
							$participant_count += empty( get_post_meta( $order_id, $field_name, true ) ) ? 0 : 1;
						}

						if ( $participant_count > 0 ) {
							$source_item = $order_item;
							$source_items_count++;
						}	// else: Not valid target, continue to next item
					}	// else: Not same quantity, continue to next item
				}

				if ( $source_items_count == 1 ) {
					$source_product_id = $source_item->get_product_id();
					$target_product_id = $target_item->get_product_id();
					$item_quant = max( $source_item->get_quantity(), $target_item->get_quantity() );	// Max, just to be safe
					for ( $participant_id = 1; $participant_id <= $item_quant; $participant_id++ ) {
						foreach ( $field_base_names as $field_base_name ) {
							$source_field_name = $source_product_id . '_' . $field_base_name . '_' . $participant_id;
							$target_field_name = $target_product_id . '_' . $field_base_name . '_' . $participant_id;
							$field_value = get_post_meta( $order_id, $source_field_name, true );
							update_post_meta( $order_id, $target_field_name, $field_value );
						}
					}
				}
			}
		}
	}

	public function menu() {
		if ( Kursoversikt::$use_tickera ) {
//			add_dashboard_page( 'Deltakere', 'Instruktørliste', 'read_participants', 'deltakere',  [ $this, 'tickera_render' ] );
		} else {
			add_dashboard_page ( 'Deltakere', 'Instruktørliste',   'read_participants', 'deltakere',     [ $this, 'woo_render'                   ] );
			add_dashboard_page ( 'Deltakere', 'Deltakereoversikt', 'read_participants', 'kontrolliste',  [ $this, 'show_not_before_participants' ] );
//			add_management_page( 'Deltakere', 'Instruktørliste', 'read_participants', 'deltakere',  [ $this, 'woo_render' ] );
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			remove_meta_box( 'postcustom', 'shop_order', 'normal' );
		}
	}

	public function dashboard_widget() {
		$new = ' &nbsp; <strong style="background-color: orangered; color: white; border: solid .2em orangered; border-radius: 1em;">NY</strong>';
		echo PHP_EOL, '<p style="font-weight: bold;">';
		$pending_pages = get_posts( [ 'post_type' => 'page', 'post_status' => [ 'pending', 'future', 'publish' ], 'posts_per_page' => 99, 'order' => 'ASC' ] );
		foreach ( $pending_pages as $page ) {
			$page_id = intval( $page->ID );
			$qu_args = $page->post_status === 'publish' ? [] : [ 'preview' => 'true' ];
			if ( get_page_template_slug( $page_id ) == 'templates/oceanwp-calendar.php' || has_shortcode( $page->post_content, 'kursoversikt' ) || has_block( 'babyswim/kursoversikt', $page_id ) ) {
				if ( $page->post_status == 'publish' || current_user_can( 'read_private_pages' ) ) {
					echo 'Se <a href="', add_query_arg( $qu_args, get_the_permalink( $page ) ), '" title="Vis siden.">', get_the_title( $page ), '</a>';
					if ( current_user_can( 'edit_page', $page_id ) ) {
						echo ' <a href="', get_edit_post_link( $page ), '" title="Rediger siden.">(rediger)', '</a>';
					}
					if ( current_user_can( 'read_participants' ) ) {
						$qu_args['participants'] = true;
						echo ' <a href="', add_query_arg( $qu_args, get_the_permalink( $page ) ), '" title="Vis deltakere.">(deltakere pr. kurs)', '</a>', $new;
					}
					echo '<br/>';
				}
			}
		}
		echo '</p>';
		if ( current_user_can( 'edit_shop_orders' ) ) {
			echo PHP_EOL, '<p title="Send tilbud.">Tilby <a href="index.php?page=preorder" title="Gå til.">forhåndsbestilling/e-post/SMS</a> &nbsp; | &nbsp; <a href="options-general.php?page=webfacing-events-page#webfacing-events-preview" title="Bestem dato.">Åpne forhåndspåmelding</a></p>';
			echo PHP_EOL, '<p style="font-weight: bold;">Se <a href="index.php?page=kontrolliste">Deltakeroversikt alle kurs</a>', $new;
			echo PHP_EOL, '<br/>Kontrollér for juks: <a href="index.php?page=kontrolliste&amp;outofstock-only=1&amp;not-participated=1&amp;pending-only=1"><em>Ikke</em> deltatt før, men nå likevel forhåndspåmeldt til et <em>fullbooket</em> upublisert kurs?</a>', $new, '</p>';
			echo PHP_EOL, '<p title="Få oversikt.">Se <a href="admin.php?page=wc-reports&amp;tab=stock&report=most_stocked" title="Se ledighet.">kurs med flest ledige plasser</a>.</p>';
		}
		if ( current_user_can( 'manage_email_logs' ) ) {
			echo PHP_EOL, '<p title="Se.">Se <a href="admin.php?page=email-log" title="Vis loggen.">e-post/SMS-logg</a>.</p>';
		}
		if ( current_user_can( 'edit_users' ) ) {
			echo PHP_EOL, '<p title="Se.">Se <a href="admin.php?page=wc-reports&tab=customers&report=customer_list" title="Vis alle kunder, alfabetisk.">kundeliste</a> &nbsp; | &nbsp; Se <a href="users.php?role=customer&orderby=registered&order=desc" title="Vis nyeste kunder.">siste registerte kunder</a>.</p>';
		}
		if ( current_user_can( 'read_participants' ) ) {
			echo PHP_EOL, '<p title="Se."><del>Få ut <a href="index.php?page=deltakere" title="Vis listene.">instruktørlister for utskrift</a>.</del><ins> <strong class="description">Se heller <q>(deltakere pr. kurs)</q> ovenfor</strong>.</ins></p>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo PHP_EOL, '<p title="Rydd.">Tøm <a href="admin.php?page=wc-status&tab=tools" title="Til statusverktøy.">kundesesjoner (fjern alle ukjøpte handlekurver)</a>.</p>';
			echo PHP_EOL, '<p title="Innstill.">Juster <a href="admin.php?page=wc-settings&tab=products&section=inventory#woocommerce_hold_stock_minutes" title="Vis innstilling for å beholde reserverte plasser.">tiden før <strong title="Venter på betaling."><em>ventende <span title="Ordre.">påmeldinger</span></em></strong> automatisk blir kansellert</a>.</p>';
			if ( class_exists( 'WC_Cart_Stock_Reducer' ) ) {
				echo PHP_EOL, '<p title="Innstill.">Juster <a href="admin.php?page=wc-settings&tab=integration&section=woocommerce-cart-stock-reducer" title="Vis innstilling.">tiden å holde på reserverte <span title="Ordre.">påmeldinger</span> i <strong><em>handlekurven</em></strong></a>.</p>';
			}
			echo PHP_EOL, '<p title="Innstill.">Bestem <a href="options-general.php?page=', Kursoversikt::$pf, 'page#', Kursoversikt::$pf, 'preview" title="Vis innstilling for varighet av forhåndsvisningslenker.">når generell forhåndspåmelding skal åpne</a>.</p>';
		}
	}
	
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', function( $hook ) {
			if ( $hook == 'dashboard_page_deltakere' ) {
				wp_enqueue_style(  'deltakere',       plugins_url( 'admin.css',       __FILE__ ), [],           Kursoversikt::$version, 'screen, aural' );
				wp_enqueue_style(  'deltakere-print', plugins_url( 'admin-print.css', __FILE__ ), [],           Kursoversikt::$version, 'print, tv' );
				wp_enqueue_script( 'deltakere',       plugins_url( 'deltakere.js',    __FILE__ ), [],           Kursoversikt::$version, false );
			} elseif ( $hook == 'post.php' ) {
				wp_enqueue_script( 'add-meta',        plugins_url( 'add-meta.js',     __FILE__ ), [ 'jquery' ], Kursoversikt::$version, false );
				echo PHP_EOL, '<style type="text/css">';
				echo PHP_EOL, ' #woocommerce-order-items .woocommerce_order_items .view .display_meta th { vertical-align: bottom; }';
				echo PHP_EOL, ' #woocommerce-order-items .woocommerce_order_items .meta .meta_items input[type="text"] { display: none; }';
				echo PHP_EOL, '</style>';
				if ( ! WP_DEBUG ) {
					echo PHP_EOL, '<script>';
					echo PHP_EOL, ' $(document).ready(function(){';
					echo PHP_EOL, "  $('[data-meta-id=\"0\"] td input').val(\"Deltaker\");";
					echo PHP_EOL, ' })';
					echo PHP_EOL, '</script>';
				}
			}
		} );

//		add_action( 'woocommerce_admin_order_item_headers',           [ $this, 'admin_order_item_headers'      ]        );
//		add_action( 'woocommerce_admin_order_item_values',            [ $this, 'admin_order_item_values'       ], 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value',      [ $this, 'order_item_display_meta_value' ], 11, 3 );
		add_action( 'woocommerce_order_item_add_action_buttons',      [ $this, 'order_item_add_action_buttons' ]        );
//		add_action( 'woocommerce_update_order',                       [ $this, 'update_order'                  ]        );
		add_action( 'save_post_shop_order',                           [ $this, 'copy_participants'             ]        );
//		add_action( 'save_post_shop_order',                           [ $this, 'xcopy_participants'            ]        );

		add_action( 'wp_dashboard_setup', function() {
			if ( current_user_can( 'read_participants' ) || current_user_can( 'read_private_pages' ) ) {
				wp_add_dashboard_widget( 'kursoversikt', 'Spesialsider for kursansvarlig', [ $this, 'dashboard_widget' ] );
			}
		} );

		add_filter( 'posts_fields', function( $fields, $wp_query ) {
			global $wpdb;
			if ( isset( $wp_query->query['label'] ) && $wp_query->query['label'] == __CLASS__ && $wp_query->query['post_type'] == 'product' ) {
				$fields .= ", WEEKDAY(CAST({$wpdb->postmeta}.meta_value AS DATE))+1 as wday";
			}
			return $fields;
		}, 10, 2 );

		add_filter( 'posts_groupby', function( $where, $wp_query ) {
			if ( isset( $wp_query->query['label'] ) && $wp_query->query['label'] == __CLASS__ && $wp_query->query['post_type'] == 'product' ) {
				global $wpdb;
				$days =  $wp_query->query['wdays'] ? implode( ',', $wp_query->query['wdays'] ) : false;
				$where .= $days ?  " HAVING wday IN ({$days})" : "";
			}
			return $where;
		}, 10, 2);

		add_filter ( 'wc_order_is_editable', function( $is_editable, $order ) {
			$is_editable = $is_editable || ( in_array( $order->get_status(), [ 'completed' ], true ) && time() - strtotime( $order->get_date_completed() ) < DAY_IN_SECONDS ) || current_user_can( 'delete_others_shop_orders' );
			return $is_editable;
		}, 11, 2 );
	}
}