<?php
//namespace Babyswim\knutsp;

/**
 * Don't call this file directly.
 */
if ( ! class_exists( 'WP' ) ) {
	die();
}

$is_pub = $post->post_status === 'publish' && ! isset( $_GET['preview'] );
$status = $is_pub ? [ 'publish' ] : [ /*'draft', */'pending', 'private', 'future' ];
$start = Kursoversikt::get_events_from();
$tax_query = [ 'relation' => 'AND',
	'cat' => [ 'taxonomy' => self::$woo_event_tax, 'field' => 'term_id', 'terms' => [ self::$woo_event_cat[ self::$woo_event_tax ]  ], 'include_children' => true ],
	'vis' => [ 'taxonomy' => 'product_visibility', 'field' => 'slug',    'terms' => [ 'exclude-from-search', 'exclude-from-catalog' ], 'operator' => 'NOT IN' ],
];
$events_query = new WP_Query( [
	'post_type'       => 'product',
	'post_status'     => $status,
	'posts_per_page'  => -1,
	'meta_key'        => Kursoversikt::pf . 'start',
	'meta_value_date' => date( 'Y-m-d', $start ),
	'meta_compare'    => '>',
	'meta_type'       => 'DATE',
	'tax_query'      => $tax_query,
	'orderby'        => 'post_title',
	'order'          => 'ASC',
	'no_found_rows'  => true,
] );
$events = $events_query->posts;

/**
 * Distributes the found events in a matrix based on location, time of day and weekday. Prime the matrix array.
 */ 
$events_matrix = [];
$terms = get_terms( [ 'taxonomy' => self::$woo_loc_tax, 'orderby' => 'order' ] );
foreach ( $terms as $term ) {
	$events_matrix[ $term->name ] = null;
}
foreach ( $events as $event ) {
	$terms = get_the_terms( $event->ID, self::$woo_loc_tax );
	$loc  = $terms ? get_the_terms( $event->ID, self::$woo_loc_tax )[0]->name : false;
	$time = self::get_event_start_time( $event );
	$hour = idate( 'H', $time );
	$min  = idate( 'i', $time );
	$wday = idate( 'w', $time );
	$float_time = floatval( $hour + ( $min * MINUTE_IN_SECONDS / HOUR_IN_SECONDS ) );
	$key_time   = strval( $float_time );
	$events_matrix[ $loc ][ $key_time ][ $wday ] = $event;
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
//		if ( current_user_can( 'install_plugins' ) ) var_dump( $loc, $day_hours );

//		$day_hours = $loc == 'Bærum' ? [ [ 16.5, 19.5 ] ] : [ [ 9., 16. ], [ 16., 20. ] ];
//		$day_hours = $loc == 'Bærum' ? [ [ 16.5, 20. ] ] : [ [ 9., 16.0 ], [ 16., 20. ] ];
		foreach( $day_hours as $day_hour ) {
			for ( $start_time = $day_hour[0]; $start_time < $day_hour[1]; $start_time += $dur ) {
				$display_times[ $day_hour[0] ][ strval( $start_time ) ] = self::fmt_display_time( $start_time );
			}
		}
?>
									<table id="<?=sanitize_key($loc)?>" role="tabpanel"<?=$iloc?' hidden="hidden"':''?> tabindex="0" aria-labelledby="label-<?=sanitize_key($loc)?>" class="tabpanel">
										<thead>
										</thead>
										<tbody>
<?php
		foreach ( $display_times as $start_time => $day_hour ) {
			$day_part = ucfirst( str_replace( '$', 's', self::day_periods[ $start_time < -$sub ? 0 : intval( ( $start_time + $sub ) * $parts / 24. ) ] ) );
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
			echo PHP_EOL, '<tr>';
			echo PHP_EOL, '<th scope="row" data-label="', $day_part, 'kurs &nbsp;">', $display_time, '-', self::fmt_display_time( $key_time + $dur ), '</th>';
			for ( $day = 1; $day <= 5; $day++ ) {
				$event = empty( $events_matrix[ $loc ][ $key_time ][ $day ] ) ? null : $events_matrix[ $loc ][ $key_time ][ $day ];
				$stock = $event ? intval( get_post_meta( $event->ID, '_stock', true ) ) : 0;
				$class = $event ? ( $stock ? 'active' : 'inactive' ) : 'none';
				$can_preview = $event ? class_exists( 'Webfacing_Public_Post_Preview' ) && Webfacing_Public_Post_Preview::is_public_preview_enabled( $event ) : false;
				$link  = $event && $stock ? ( $is_pub || ! $can_preview ? get_the_permalink( $event ) : Webfacing_Public_Post_Preview::get_preview_link( $event ) ) : false;
				$title = $event ? get_the_terms( $event->ID, 'product_cat' )[0]->name : '';
				echo PHP_EOL, '<td class="', $class, '" data-label="', $weekday[ $day ],'"><a', $link ? ' href="' . $link . '"' : '', '>', $title, '<br/>', $event ? $stock . ' ledig' . ( $stock == 1 ? '' : 'e' ) : '', '</a></td>';
			}
			echo PHP_EOL, '</tr>';
		}
	}
?>
										</tbody>
										<tfoot>
											<tr style="height: 0;"><td colspan="6" style="text-align: center; font-size: x-small; line-height: 110%; padding: 5px;">Oversikt laget av <a href="https://nettvendt.no/" target="_blank" rel="noopener noreferrer" style="display: inline;">Nettvendt</a></tD></tr>
										</tfoot>
									</table>
<?php
	}
}
