<?php
//namespace Babyswim\knutsp;

/**
 * Don't call this file directly.
 */
if ( ! class_exists( 'WP' ) ) {
	die();
}

$partic = isset( $_GET['participants'] ) && is_user_logged_in() && current_user_can( 'read_participants' );
$is_pub = $post->post_status === 'publish' && ! isset( $_GET['preview'] );
$status = $is_pub ? [ 'publish' ] : [ 'pending', 'private', 'future' ];
$start  = Kursoversikt::get_upcoming_events_from();
$tax_query = [ 'relation' => 'AND',
	'cat' => [ 'taxonomy' => self::$woo_event_tax, 'field' => 'term_id', 'terms' => [ self::$woo_event_cat[ self::$woo_event_tax ]  ], 'include_children' => true ],
	'vis' => [ 'taxonomy' => 'product_visibility', 'field' => 'slug',    'terms' => [ 'exclude-from-search', 'exclude-from-catalog' ], 'operator' => 'NOT IN' ],
];
$events_query = new WP_Query( [
	'post_type'      => 'product',
	'post_status'    => $status,
	'posts_per_page' => Kursoversikt::$events_max,
	'meta_key'       => Kursoversikt::pf . 'start',
	'meta_value'     => date_i18n( 'Y-m-d', $start ),
	'meta_compare'   => '>=',
	'meta_type'      => 'DATE',
	'tax_query'      => $tax_query,
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
	'no_found_rows'  => true,
] );
$event_posts = $events_query->posts;

/**
 * Distributes the found events in a matrix based on location, time of day and weekday. Prime the matrix array.
 */
$transient_name = Kursoversikt::pf . 'events_' . get_the_id();
$transient_time = ( WP_DEBUG ? 1 : Kursoversikt::transient_time ) * MINUTE_IN_SECONDS;
$events_matrix  = get_transient( $transient_name );
//delete_transient( $transient_name );
$events_cached  = is_array( $events_matrix ) && count( $events_matrix );
if ( ! $events_cached ) {
	$events_matrix = [];
	$terms = get_terms( [ 'taxonomy' => self::$woo_loc_tax, 'orderby' => 'order', 'hide_empty' => true ] );
	foreach ( $terms as $term ) {
		$events_matrix[ $term->name ] = null;
	}
	foreach ( $event_posts as $event_post ) {
		$event = new stdClass;
		$event->ID = $event_post->ID;
		$terms = get_the_terms( $event_post->ID, self::$woo_loc_tax );
		$loc   = $terms ? $terms[0]->name : false;
//		$lo_id = $terms ? $terms[0]->term_id : false;
		$time  = self::get_event_start_time( $event_post );
		$event->event_time  = $time;
		$event->event_title = get_the_terms( $event_post->ID, 'product_cat' )[0]->name;
		$event->event_stock = intval( get_post_meta( $event_post->ID, '_stock', true ) );
		$event->can_preview = class_exists( 'Webfacing_Public_Post_Preview' ) && Webfacing_Public_Post_Preview::is_public_preview_enabled( $event_post );
		$event->event_link  = $event->can_preview ? Webfacing_Public_Post_Preview::get_preview_link( $event_post ) : get_the_permalink( $event_post );
		$event->loc_id      = $terms ? $terms[0]->term_id : false;
		$hour = idate( 'H', $time );
		$min  = idate( 'i', $time );
		$wday = idate( 'w', $time );
		$float_time = floatval( $hour + ( $min * MINUTE_IN_SECONDS / HOUR_IN_SECONDS ) );
		$key_time   = strval( $float_time );
		$events_matrix[ $loc ][ $key_time ][ $wday ] = $event;
	}
	if ( is_array( $event_posts ) && count( $event_posts ) ) {
		set_transient( $transient_name, $events_matrix, $transient_time );
	} else {
		delete_transient( $transient_name );
	}
}
?>	
									<div role="tablist" class="tablist" aria-label="<?=get_the_title()?>">
<?php
/**
 * Loops through each found location to create tab buttons.
 */
$iloc = 0;
foreach ( array_keys( $events_matrix ) as $loc ) {
	if ( $loc && is_array( $events_matrix[ $loc ] ) && count( $events_matrix[ $loc ] ) ) {
?>
										<button id="label-<?=sanitize_key($loc)?>" role="tab"<?=$loc?' tabindex="-1"':''?>  aria-current="<?=$iloc?'false':'location'?>" aria-controls="<?=sanitize_key($loc)?>" class="tab<?=$iloc?'':' active'?>" onclick="openTab(event, '<?=sanitize_key($loc)?>');"><?=esc_attr($loc)?></button>
<?php
		$iloc++;
	}
}
?>
									</div><br style="clear: left;" />
<?php
if ( count( $event_posts ) ) {
	/**
	 * Loops through each found location to create a table for each.
	 */
	$dur = Kursoversikt::get_event_duration();
	$iloc = 0;
	foreach ( array_keys( $events_matrix ) as $loc ) {
		if ( $loc && is_array( $events_matrix[ $loc ] ) && count( $events_matrix[ $loc ] ) ) {
			$parts = floatval( count( self::day_periods ) );
	//		$sub = -1.;
			$sub = self::day_periods_off;

			/**
			* Sets up the times (course slots) of the day to be used for display in left column.
			*/ 
			$display_times = [];
			$key_day_hours = array_keys( $events_matrix[ $loc ] );
			sort ( $key_day_hours, SORT_NUMERIC );
			$day_hours = [];
			$ptime = 0.;
			$pkey  = -1;
			for( $stime = 0.; $stime < 24.; $stime += $dur ) {
				$skey = $stime < -$sub ? 0 : intval( ( $stime + $sub ) * $parts / 24. );
				if ( in_array( $stime, $key_day_hours ) ) {
					if ( $skey > $pkey ) {
						$day_hours[ $skey  ][0] = $stime;
						if ( isset( $day_hours[ $pkey ][0] ) ) {
							$day_hours[ $pkey ][1] = $ptime + $dur;
						}
					}
					$pkey  = $skey;
					$ptime = $stime;
				}
			}
			if ( isset( $day_hours[ $pkey ][0] ) ) {
				$day_hours[ $pkey ][1] = $ptime + $dur;
			}
			foreach( $day_hours as $day_hour ) {
				for ( $start_time = $day_hour[0]; $start_time < $day_hour[1]; $start_time += $dur ) {
					$display_times[ $day_hour[0] ][ strval( $start_time ) ] = self::fmt_display_time( $start_time );
				}
			}
	?>
										<table id="<?=sanitize_key($loc)?>" role="tabpanel"<?=$iloc?' hidden="hidden"':''?> tabindex="0" aria-labelledby="label-<?=sanitize_key($loc)?>" class="tabpanel"<?=current_user_can('manage_options')?' title="'.($events_cached?'bufret':'fresh'):''?>">
	<?php
			if ( $partic ) {
				echo PHP_EOL, ' <caption style="color: orangered; text-align: center;">OBS: Lenker til deltakeroversikt/instruktørliste</caption>';
			}
			foreach ( $display_times as $start_time => $day_hour ) {
				$day_part = ucfirst( str_replace( '$', 's', self::day_periods[ $start_time < -$sub ? 0 : intval( ( $start_time + $sub ) * $parts / 24. ) ] ) );
				echo PHP_EOL, ' <tbody id="h-', $start_time, '">';
	?>

												<tr>
													<th scope="col"><?=$day_part?>&shy;kurs</th>
	<?php
			$iloc++;
			/**
			 * Loops through the days for the other column headers.
			 */ 
			for ( $day = 1; $day <= 5; $day++ ) {
	?>
													<th scope="col" class="weekday"><?=$weekday[$day]?></th>
	<?php
			}
	?>
												</tr>
	<?php
			/**
			* Loops through display times for the rows.
			*/
			foreach ( $day_hour as $key_time => $display_time ) {
				echo PHP_EOL, '  <tr>';
				echo PHP_EOL, '   <th scope="row" data-label="', $day_part, 'kurs &nbsp;">', $display_time, '-', self::fmt_display_time( $key_time + $dur ), '</th>';
				for ( $day = 1; $day <= 5; $day++ ) {
					$event = empty( $events_matrix[ $loc ][ $key_time ][ $day ] ) ? null : $events_matrix[ $loc ][ $key_time ][ $day ];
//					$time = Kursoversikt::get_event_start_time( $event );
					$started = $event ? ( current_time( 'U' ) > $event->event_time ? ' og er nå i gang' : '' ) : '';
//					$stock = $event && $event->event_stock ? intval( get_post_meta( $event->ID, '_stock', true ) ) : 0;
					$prod  = $event ? wc_get_product( $event->ID ) : null;
					$stock = $prod  ? $prod->get_stock_quantity() : 0;
					$class = $event ? ( $stock ? 'active' : 'inactive' ) : 'none';
	//				$can_preview = $event ? class_exists( 'Webfacing_Public_Post_Preview' ) && Webfacing_Public_Post_Preview::is_public_preview_enabled( $event ) : false;
	//				$can_preview = $event ? $event->can_preview : false;
	//				$link  = $event && $stock ? ( $is_pub || ! $can_preview ? get_the_permalink( $event ) : Webfacing_Public_Post_Preview::get_preview_link( $event ) ) : false;
					$link  = $event && $stock ? $event->event_link : false;
	//				$link  = ! $is_pub || get_post_meta( $event->ID, Kursoversikt::pf . 'links_active', true ) ? $link : false;
//					$link  = $is_pub || Kursoversikt::$preview_links ? $link : false;
					$link  = Kursoversikt::$preview_links ? $link : false;
					$title = $event ? $event->event_title : '';
					$link  = $partic && $event ? htmlspecialchars( add_query_arg( [ 'page' => 'deltakere', 'event' => intval( $event->ID ) ], admin_url( '.' ) ) . '#printarea-' . $event->loc_id ) : $link;
					echo PHP_EOL, '   <td class="', $class, '" data-label="', $weekday[ $day ],'"><a', $link ? ' href="' . $link . '"' : '', ' title="Starte', $started ? 't' : 'r', ' ', $event ? date_i18n( 'd.m.y \k\l H:i', $event->event_time ) : '', $started, '.">', $title, '<br/>', $event ? ( $stock == 0 ? 'Ingen' : $stock ) . ' ledig' . ( $stock == 1 ? '' : 'e' ) : '', '</a></td>';
				}
				echo PHP_EOL, '  </tr>';
			}
			echo PHP_EOL, ' </tbody>';
		}
	?>
											<tfoot>
												<tr style="height: 0;"><td colspan="6" style="text-align: center; font-size: x-small; line-height: 110%; padding: 5px;">Oversikt laget av <a href="https://nettvendt.no/" target="_blank" rel="noopener noreferrer" style="display: inline;">Nettvendt</a></td></tr>
											</tfoot>
										</table>
	<?php
		}
	}
} else {
	$posts = get_posts( [ 'post_type' => 'product', 'post_status' => [ 'pending','future' ], 'order' => 'ASC', 'posts_per_page' => 1 ] );
	$date = $is_pub && $posts && count( $posts ) && $posts[0] ? mysql2date( 'U', $posts[0]->post_date ) : ( Kursoversikt::$preview_links ? current_time( 'U' ) + WEEK_IN_SECONDS  : Kursoversikt::$preview_dt );
	echo PHP_EOL, '<p><strong>Ingen kurs publisert akkurat nå. Sjekk igjen etter ', date_i18n( 'l j. F Y \k\l H.i', $date ), '.</strong><br />&nbsp;</p>';
}