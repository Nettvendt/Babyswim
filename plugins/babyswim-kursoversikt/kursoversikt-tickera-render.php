<?php
	global $post, $weekday;
	$is_pub = $post->post_status === 'publish';
	$status = $is_pub ? [ 'publish' ] : [ 'draft', 'pending', 'private', 'future' ];
	$start  = current_time( 'U' ) - ( self::WEEKS * ( WEEK_IN_SECONDS - 1 ) + ( 2 * DAY_IN_SECONDS ) );	// Start time not older than 8 weeks - 2 days
	$end    = current_time( 'U' ) - ( HOUR_IN_SECONDS / 2 );											// End time not passed in half an hour
	$meta_query = [
		[ 'key' => 'event_date_time',     'value' => $start, 'compare' => '>=', 'type' => 'DATETIME' ],
		[ 'key' => 'event_end_date_time', 'value' => $end,   'compare' => '>=', 'type' => 'DATETIME' ],
	];
	$event_query = new WP_Query( [
		'post_type'      => 'tc_events',
		'post_status'    => $status,
		'meta_key'       => 'event_location',
		'meta_query'     => $meta_query,
		'posts_per_page' => -1,
		'orderby'        => 'event_location',
		'order'          => 'DESC',
		'fields'         => [ 'ID', 'post_title' ],
		'no_found_rows'  => true,
	] );
	$events = $event_query->posts;

	/**
	 * Distributes the found events in a matrix based on location, time of day and weekday.
	 */ 
	$events_matrix = [];
	foreach ( $events as $event ) {
		$loc  = get_post_meta( $event->ID, 'event_location', true );
		$time = mysql2date( 'U', get_post_meta( $event->ID, 'event_date_time', true ) );
		$hour = idate( 'G', $time );
		$min  = idate( 'i', $time );
		$wday = idate( 'w', $time );
		$time = strval( $hour + ( $min * MINUTE_IN_SECONDS / HOUR_IN_SECONDS  ) );
		$tax_query = $is_pub ? [ [
			'taxonomy' => 'product_visibility',
			'field'    => 'slug',
			'terms'    => [ 'exclude-from-search', 'exclude-from-catalog' ],
			'operator' => 'NOT IN',
		] ] : [];
		$meta_query = [ [ 'key' => '_event_name', 'value' => $event->ID, 'compare' => '=', 'type' => 'NUMERIC' ] ];
		$ticket = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => $status,
			'tax_query'      => $tax_query,
			'meta_query'     => $meta_query,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );
		$event->ticket = $ticket && is_array( $ticket->posts ) && count( $ticket->posts ) ? $ticket->posts[0] : null;
		$events_matrix[ $loc ][ $time ][ $wday ] = $event;
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
											<button id="label-<?=sanitize_key($loc)?>" role="tab"<?=$loc?' tabindex="-1"':''?>"  aria-current="<?=$iloc?'false':'location'?>" aria-controls="<?=sanitize_key($loc)?>" class="tab<?=$iloc?'':' active"'?>" onclick="openTab(event, '<?=sanitize_key($loc)?>');"><?=esc_attr($loc)?></button>
<?php
			$iloc++;
		}
	}
?>
										</div><br style="clear: left;"/>
<?php	
	/**
	 * Loops through each found location to create a table for each.
	 */
	$iloc = 0;
	foreach ( array_keys( $events_matrix ) as $loc ) {
		if ( $loc && is_array( $events_matrix[ $loc ] ) && count( $events_matrix[ $loc ] ) ) {
?>
										<table id="<?=sanitize_key($loc)?>" role="tabpanel"<?=$iloc?' hidden="hidden"':''?> tabindex="0" aria-labelledby="label-<?=sanitize_key($loc)?>" class="tabpanel">
											<thead>
												<tr>
													<th scope="col">Ettermid&shy;dags&shy;kurs</th>
<?php
			$iloc++;
			/**
			 * Loops through the days for the other column headers.
			 */ 
			for ( $day = 1; $day <=5; $day++ ) {
?>
													<th scope="col"><?=$weekday[ $day ]?></th>
<?php
			}
?>
												</tr>
											</thead>
											<tbody>
<?php
			/**
			* Sets up the times (course slots) of the day to be used for display in left column.
			*/ 
			$display_times = [];
			for ( $start_time = 16; $start_time <= 19; $start_time += .5 ) {
				$display_times[ strval( $start_time ) ] = self::fmt_display_time( $start_time );
			}

			/**
			* Loops through display times for the rows.
			*/ 
			foreach ( $display_times as $key_time => $display_time ) {
				echo PHP_EOL, '<tr>';
				echo PHP_EOL, '<th scope="row" data-label="Ettermid&shy;dags&shy;kurs &nbsp;">', $display_time, '-', self::fmt_display_time( $key_time +.5 ), '</th>';
				for ( $day = 1; $day <=5; $day++ ) {
					$event = empty( $events_matrix[ $loc ][ $key_time ][ $day ] ) ? null : $events_matrix[ $loc ][ $key_time ][ $day ];
					$stock = $event && $event->ticket ? intval( get_post_meta( $event->ticket->ID, '_stock', true ) ) : 0;
//					$style = $event && $event->ticket ? ( $stock ? '' : ' style="background-color: pink;"' ) : '';
					$class = $event && $event->ticket ? ( $stock ? 'active' : 'inactive' ) : 'none';
					$link  = $event && $event->ticket && $stock ? get_the_permalink( $event->ticket ) : false;
					$title = $event && $event->ticket ? mb_str_replace( [ ' år ', ' mnd ', ' måneder ' ], [ ' år<br/>', ' mnd<br/>', ' måneder<br/>' ], esc_attr( $event->ticket->post_title ) ) : '';
					echo PHP_EOL, '<td class="', $class, '" data-label="', $weekday[ $day ],'"><a', $link ? ' href="' . $link . '"' : '', '>', $title, '<br/>', $event ? $stock . ' ledige' : '', '</a></td>';
				}
				echo PHP_EOL, '</tr>';
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
