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
		'navn' => 'Navn',
		'fodt' => 'Født',
	];

	public static function get_order_ids_by_product( int $product_id, array $statuses = [ 'completed', 'processing', 'on-hold' ] ): array {
		global $wpdb;
		$prefix = 'wc-';

		$table_posts = $wpdb->prefix . "posts";
		$table_items = $wpdb->prefix . "woocommerce_order_items";
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
			ORDER BY $table_items.order_item_id DESC"
		);
		$orders_ids = is_array( $orders_ids ) ? $orders_ids : [];
		return $orders_ids;
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
		<h3>Deltakerliste kan også velges direkte fra <a href="edit.php?post_type=product"><?=get_post_type_labels(get_post_type_object('product'))->menu_name?></a>, kolonne &laquo;<abbr title="Instruktør">In</abbr>&raquo;</h3>
		<div role="tablist" aria-label="<?=get_admin_page_title()?>">
<?php
		$is_post = is_Admin() && current_user_can( 'read_participants' ) && $_SERVER['REQUEST_METHOD'] === 'POST';
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
			$sql_start = date( 'Y-m-d', $start );
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
					$order_ids = self::get_order_ids_by_product( $event_id );
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
				echo PHP_EOL, '<input type="hidden" name="loc" value="' . intval( $loc_id ) . '" />';
				echo PHP_EOL, '</select> <small>(', $event_count, ')</small>';
				echo PHP_EOL, '<noscript> &nbsp; <button type="submit" style="height: 2.2em; margin: .8px; padding: 2px; vertical-align: middle;">Vis tabell</button></noscript>';
			} else {
				echo PHP_EOL, '<p>Ingen kurs funnet. Prøv endre sted eller filtrene.</p>';
			}
?>
			<br/><br/>
			</div><!-- search -->
<?php
				echo PHP_EOL, '</form>', PHP_EOL;
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
				$order_ids = self::get_order_ids_by_product( $event_id, [ 'completed' ] );
				foreach ( $order_ids as $order_id ) {
					$order = wc_get_order( $order_id );
					$order_billing = get_post_meta( $order_id, 'billing_before', true );
					$order_items = $order->get_items();
					foreach ( $order_items as $order_item_id => $order_item ) {//var_dump( $order_item );
						if ( $order_item->get_product_id() == $event_id ) {
							$quantity = $order_item->get_quantity();
							if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) {
								$refunded = $order->get_qty_refunded_for_item( $order_item_id );
								$quantity += $refunded;
							}
							for ( $child_id = 1; $child_id <= $quantity; $child_id++ ) {
								echo '<tr title="Kurs-ID: ', $event_id, ' ', $refunded,'.">';
								echo '<td class="square"><div> &nbsp; <br/> &nbsp; </td>';
								foreach ( $field_first_names as $field_first_name ) {
									$field_name = $event_id . '_' . $field_first_name . '_' . $child_id;
									$field_value = esc_attr( get_post_meta( $order_id, $field_name, true ) );
									$field_value = $field_value && $field_first_name == 'fodt' ? date_i18n( 'd.m.y', strtotime( $field_value ) ) : $field_value;
									$field_value = $field_value ?: '<small style="font-size: small; font-weight: normal;">(mangler)</small>';
									echo '<td title="Feltnavn: ', esc_attr( $field_name ), '.">', $field_value, '</td>';
								}
								echo '<td>', $order->get_billing_first_name(), ' ', $order->get_billing_last_name(), '</td>';
								echo '<td>', $order->get_billing_phone(), '</td>';
								echo '<td>', $order_billing, '</th>';
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
?>
	</div><!-- wrap -->
<?php
	}

	public function tickera_render() {
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
		echo '<th class="quantity">Deltakere</th>';
	}
	
	public function admin_order_item_values( $product, $item ) {
		$fields = Kursoversikt_Deltakere::WC_ESC_FIELDS;
		$field_first_names = array_keys( $fields );

		if ( method_exists( $item, 'get_product_id' ) ) {
			$product_id = $item->get_product_id();
			$order_id = $item->get_order_id();
			$item_quant = $item->get_quantity();
			$title   = '';
			$display = '';
			$hellip  = '';
			$field_value_count = 0;
			for ( $child_id = 1; $child_id <= $item_quant; $child_id++ ) {
				foreach ( $field_first_names as $field_first_name ) {
					$field_name = $product_id . '_' . $field_first_name . '_' . $child_id;
					$field_value = get_post_meta( $order_id, $field_name, true );
					if ( $field_value ) {
						$field_value = $field_first_name == 'fodt' ? date_i18n( 'd.m.y', strtotime( $field_value ) ) : $field_value;
						$field_value_count++;
						$title   .= $fields[ $field_first_name ] . ': ' . $field_value . ', ';
						$display .= $child_id <= 2 && $field_first_name == 'navn' ? $field_value . ',' : '';
						$hellip  .= $child_id >  2 ? '&hellip;' : '';
					}
				}
			}
			$title   = rtrim( $title,   ', ' );
			$display = rtrim( $display, ', ' ) . $hellip;
			$participants_count = $field_value_count / count( $field_first_names ); 
			if ( $participants_count ) {
				echo '<td title="', esc_attr( $title ), '.">', $display, ' (', $participants_count . ')</td>';
			} else {
				echo '<td title="(', $participants_count,')."> &nbsp; &nbsp; </td>';
			}
		}
	}

	public function order_item_add_action_buttons( $order ) {
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
	
	public function copy_participants( $order_id ) {
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

				} else {	// Zero or more than one source items with participants
					WC_Admin_Notices::remove_all_notices();
					WC_Admin_Notices::add_custom_notice( 'warning', ( $source_items_count ? 'Flere enn ett' : 'Ingen' ) .' kurs å kopiere deltakere fra.' );
//					wc_add_notice( ( $source_items_count ? 'Flere enn ett' : 'Ingen' ) .' kurs å kopiere deltakere fra.', 'warning' );
					}
			} else {	// Zero or more than one target items without participants
				WC_Admin_Notices::remove_all_notices();
				WC_Admin_Notices::add_custom_notice( 'warning', ( $target_items_count ? 'Flere enn ett' : 'Ingen' ) .' kurs å kopiere deltakere til.' );
//				wc_add_notice( ( $target_items_count ? 'Flere enn ett' : 'Ingen' ) .' kurs å kopiere deltakere til.','warning' );
			}
		}
	}

	public function menu() {
		if ( Kursoversikt::$use_tickera ) {
			add_dashboard_page( 'Deltakere', 'Instruktørliste', 'read_participants', 'deltakere',  [ $this, 'tickera_render' ] );
		} else {
			add_dashboard_page ( 'Deltakere', 'Instruktørliste', 'read_participants', 'deltakere',  [ $this, 'woo_render' ] );
			add_management_page( 'Deltakere', 'Instruktørliste', 'read_participants', 'deltakere',  [ $this, 'woo_render' ] );
		}
	}

	public function dashboard_widget() {
		echo PHP_EOL, '<p>Se ';
		$pending_pages = get_posts( [ 'post_type' => 'page', 'post_status' => [ 'pending', 'future', 'publish' ], 'posts_per_page' => -1 ] );
		foreach ( $pending_pages as $page ) {
			$page_id = intval( $page->ID );
			if ( get_page_template_slug( $page_id ) == 'templates/oceanwp-calendar.php' || has_shortcode( $page->post_content, 'kursoversikt' ) || has_block( 'babyswim/kursoversikt', $page_id ) ) {
				if ( $page->post_status == 'publish' || current_user_can( 'read_private_pages' ) ) {
					echo '<a href="', get_the_permalink( $page ), '" title="Vis siden.">', get_the_title( $page ), '</a>';
					if ( current_user_can( 'edit_page', $page_id ) ) {
						echo ' <a href="', get_edit_post_link( $page ),'" title="Rediger siden.">(rediger)','</a>';
					}
					echo ' &nbsp; ';
				}
			}
		}
		echo '</p>';
		if ( current_user_can( 'edit_shop_orders' ) ) {
			echo PHP_EOL, '<p title="Se.">Tilby <a href="index.php?page=preorder" title="Gå til.">forhåndsbestilling/e-post/SMS</a></p>';
		}
		if ( current_user_can( 'manage_email_logs' ) ) {
			echo PHP_EOL, '<p title="Se.">Se <a href="admin.php?page=email-log" title="Vis loggen.">e-post/SMS-logg</a><!-- &nbsp; <a href="admin.php?page=twilio-options&tab=logs" title="Vis loggen.">SMS-logg</a-->.</p>';
		}
		if ( current_user_can( 'edit_users' ) ) {
			echo PHP_EOL, '<p title="Se.">Se <a href="users.php?role=customer&orderby=registered&order=desc" title="Vis kunder.">siste registerte kunder</a>.</p>';
		}
		if ( current_user_can( 'read_participants' ) ) {
			echo PHP_EOL, '<p title="Se.">Få ut <a href="index.php?page=deltakere" title="Vis listene.">instruktørlister for utskrift</a>.</p>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo PHP_EOL, '<p title="Rydd.">Tøm <a href="admin.php?page=wc-status&tab=tools" title="Til statusverktøy.">kundesesjoner (fjern alle ukjøpte handlekurver)</a>.</p>';
			echo PHP_EOL, '<p title="Innstill.">Juster <a href="admin.php?page=wc-settings&tab=products&section=inventory#woocommerce_hold_stock_minutes" title="Vis innstilling for å beholde reserverte plasser.">tiden før <strong title="Venter på betaling."><em>ventende <span title="Ordre.">påmeldinger</span></em></strong> automatisk blir kansellert</a>.</p>';
			if ( class_exists( 'WC_Cart_Stock_Reducer' ) ) {
				echo PHP_EOL, '<p title="Innstill.">Juster <a href="admin.php?page=wc-settings&tab=integration#woocommerce_woocommerce-cart-stock-reducer_expire_time" title="Vis innstilling.">tiden å holde på reserverte <span title="Ordre.">påmeldinger</span> i <strong><em>handlekurven</em></strong></a>.</p>';
			}
			echo PHP_EOL, '<p title="Innstill.">Juster <a href="options-general.php?page=', Kursoversikt::$pf, 'page#', Kursoversikt::$pf, 'preview" title="Vis innstilling for varighet av forhåndsvisningslenker.">tiden før lenker til forhåndsvisning blir ugyldige</a>.</p>';
		}
	}
	
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', function( $hook ) {
			if ( $hook == 'dashboard_page_deltakere' ) {
				wp_enqueue_style(  'deltakere',       plugins_url( 'admin.css',       __FILE__ ), [], Kursoversikt::$version, 'screen, aural' );
				wp_enqueue_style(  'deltakere-print', plugins_url( 'admin-print.css', __FILE__ ), [], Kursoversikt::$version, 'print, tv' );
				wp_enqueue_script( 'deltakere',       plugins_url( 'deltakere.js',    __FILE__ ), [], Kursoversikt::$version, false );
			}
		} );

		add_action( 'woocommerce_admin_order_item_headers',           [ $this, 'admin_order_item_headers'      ]        );
		add_action( 'woocommerce_admin_order_item_values',            [ $this, 'admin_order_item_values'       ], 10, 2 );
		add_action( 'woocommerce_order_item_add_action_buttons',      [ $this, 'order_item_add_action_buttons' ]        );
		add_action( 'woocommerce_update_order',                       [ $this, 'update_order'                  ]        );
		add_action( 'save_post_shop_order',                           [ $this, 'copy_participants'             ]        );

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
	}
}