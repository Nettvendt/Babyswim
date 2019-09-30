<?php
/*
Plugin Name: Ekstra tellere i akkurat nå
Description: Teller for tilbakemeldinger i Akkurat Nå-boksen i kontrollpanelet.
Author: Mika Epstein, Knut Sparhell
Author URI: https://knut.sparhell.no/
Plugin URI: https://halfelf.org/2018/show-feedback-in-right-now/
Version: 1.1
License: GPL

Opphavsretten tilhører Mika Epstein, med endringer av Knut Sparhell (epost: knut@sparhell.no)
Dette programmet (utvidelser til WordPress) er fri programvare. Du kan redistribuere og/eller endre det
under betingelsene til GNU General Public License (GPL), slik den er publisert av Free Software Foundation.
*/

/*
 * Show Feedback in "Right Now"
 */

add_action( 'dashboard_glance_items', function () {
	foreach ( [ 'attachment' => [ 'mediefil', 'mediefiler' ] ] as $post_type => $args ) {
		$num_posts   = wp_count_posts( $post_type );
		$count_posts = intval( isset( $num_posts->inherit ) ? $num_posts->inherit : 0 );
		$text = _n( '%d ' . $args[0], '%d ' . $args[1], $count_posts );
		$text = sprintf( $text, number_format_i18n( $count_posts ) );
		if ( current_user_can( get_post_type_object( $post_type )->cap->edit_posts ) ) {
			printf( '<li class="%1$s-count"><a href="upload.php">%2$s</a></li>', esc_attr( $post_type ), wp_kses_post( $text ) );
		} else {
			printf( '<li class="%1$s-count"><span>%2$s</span></li>', esc_attr( $post_type ), wp_kses_post( $text ) );
		}
	}
} );

add_action( 'dashboard_glance_items', function () {
	foreach ( [
		'product,1'   => [ 'publisert kurs', 'publiserte kurs' ],
		'product,2'   => [ 'planlagt kurs', 'planlagte kurs', [ 'pending' => true, 'future' => true ] ],
//		'testimonial' => [ 'vurdering', 'vurderinger'],
//		'portfolio'   => [ 'portefølje', 'porteføljer'],
		'shop_order'  => [ 'påmelding', 'påmeldinger', [ 'wc-pending' => true, 'wc-processing' => true, 'wc-on-hold' =>true, 'wc-completed' => true ] ],
//		'price_table' => [ 'pristabell', 'pristabeller'],
//		'gallery'     => [ 'galleri', 'gallerier'],
//		'feedback'    => [ 'tilbakemelding', 'tilbakemeldinger' ],
//		'kjm_notice'  => [ 'internt varsel', 'interne varsler', 'private' ],
		'wp-help'     => [ 'hjelpedokument', 'hjelpedokumenter' ],
	] as $post_type => $args ) {
		$post_type = explode( ',', $post_type )[0];
		$num_posts   = wp_count_posts( $post_type );
		$statuses = isset( $args[2] ) ? $args[2] : [ 'publish' => true ];
		$count_posts = 0;
		foreach ( $statuses as $status => $name ) {
			$count_posts += intval( isset( $num_posts->$status ) ? $num_posts->$status : 0 );
		}
		if ( $count_posts ) {
			$text = _n( '%d ' . $args[0], '%d ' . $args[1], $count_posts );
			$text = sprintf( $text, number_format_i18n( $count_posts ) );
			if ( current_user_can( get_post_type_object( $post_type )->cap->edit_posts ) ) {
				printf( '<li class="%1$s-count"><a href="edit.php?post_type=%1$s">%2$s</a></li>', esc_attr( $post_type ), wp_kses_post( $text ) );
			} else {
				printf( '<li class="%1$s-count"><span>%2$s</span></li>', esc_attr( $post_type ), wp_kses_post( $text ) );
			}
		}
	}
}, 12 );

add_action( 'dashboard_glance_items', function () {
	foreach ( [ 'plugin' => [ 'aktiv utvidelse', 'aktive utvidelser' ] ] as $plugin => $args ) {
		$plugins = array_keys( get_plugins() );
		$count_plugins = 0;
		foreach ( $plugins as  $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$count_plugins++;
			}
		}
		$text = _n( '%d ' . $args[0], '%d ' . $args[1], $count_plugins );
		$text = sprintf( $text, number_format_i18n( $count_plugins ) );
		if ( current_user_can( 'install_plugins' ) ) {
			printf( '<li class="%1$s-count"><a href="plugins.php?plugin_status=active">%2$s</a></li>', 'plugin', wp_kses_post( $text ) );
		} else {
			printf( '<li class="%1$s-count"><span>%2$s</span></li>', 'plugin', wp_kses_post( $text ) );
		}
	}
}, 14 );

add_action( 'dashboard_glance_items', function() {
	foreach ( [
		'administrator' => [ 'administrator', 'administratorer' ],
		'editor'        => [ 'redaktør', 'redaktører'],
		'shop_manager'  => [ 'butikksjef', 'butikksjefer'],
		'office_worker' => [ 'kontormedarbeider', 'kontormedarbeidere'],
		'instructor'    => [ 'instruktør', 'instruktører'],
		'customer'      => [ 'kunde', 'kunder'],
		'contributor'   => [ 'bidragsyter', 'bidragsytere'],
		'subscriber'    => [ 'tidligere bruker', 'tidligere brukere'],
		] as $role => $args ) {
		$count_users = count_users();
		$count_users = isset( $count_users['avail_roles'][ $role ] ) ? $count_users['avail_roles'][ $role ] : 0;
		$text = _n( '%d ' . $args[0], '%d ' . $args[1], $count_users );
		$text = sprintf( $text, number_format_i18n( $count_users ) );
		if ( $count_users ) {
			if ( current_user_can( 'list_users' )/* || current_user_can( $role )*/ ) {
				printf( '<li class="%1$s-count"><a href="users.php?role=%1$s">%2$s</a></li>', $role, wp_kses_post( $text ) );
			} else {
				printf( '<li class="%1$s-count"><span>%2$s</span></li>', $role, wp_kses_post( $text ) );
			}
		}
	}
}, 99 );

/*
 * Custom Icon for Feedback in "Right Now"
 */
add_action( 'admin_head', function () {
	?>
	<style type='text/css'>
	<?php
	if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'contact-form' ) ) {
		?>
		#dashboard_right_now li.feedback-count a:before {
			content: '\f175';
			margin-left: -1px;
		}
	<?php
	}
	?>
	#dashboard_right_now li.kjm_notice-count ::before, .menu-icon-kjm_notice .dashicons-admin-post::before {
		content: '\f534';
		margin-left: -1px;
	}

	#dashboard_right_now li.product-count ::before {
		content: '\f313';
		margin-left: -1px;
	}

	#dashboard_right_now li.shop_order-count ::before {
		content: '\f174';
		margin-left: -1px;
	}

	#dashboard_right_now li.wp-help-count ::before {
		content: '\f223';
		margin-left: -1px;
	}

	#dashboard_right_now li.attachment-count ::before {
		content: '\f104';
		margin-left: -1px;
	}
	#dashboard_right_now li.plugin-count ::before {
		content: '\f106';
		margin-left: -1px;
	}
	#dashboard_right_now li.administrator-count ::before {
		content: '\f338';
		margin-left: -1px;
	}
	#dashboard_right_now li.editor-count ::before {
		content: '\f110';
		margin-left: -1px;
	}
	#dashboard_right_now li.shop_manager-count ::before {
		content: '\f12f'; 
		margin-left: -1px;
	}
	#dashboard_right_now li.office_worker-count ::before {
		content: '\f12f'; 
		margin-left: -1px;
	}
	#dashboard_right_now li.instructor-count ::before {
		content: '\f468'; \f12f
		margin-left: -1px;
	}
	#dashboard_right_now li.customer-count ::before {
		content: '\f336';
		margin-left: -1px;
	}
	#dashboard_right_now li.contributor-count ::before {
		content: '\f336';
		margin-left: -1px;
	}
	#dashboard_right_now li.subscriber-count a::before {
		content: '\f448';
		margin-left: -1px;
	}
	</style>
	<?php
} );
