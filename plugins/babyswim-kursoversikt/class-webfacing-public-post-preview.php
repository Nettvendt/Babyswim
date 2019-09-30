<?php
/**
 * Don't call this file directly.
 */
if ( ! class_exists( 'WP' ) ) {
	die();
}

/**
 * Subclass
 */
class Webfacing_Public_Post_Preview extends DS_Public_Post_Preview {

	/**
	 * Returns the post ids which are registered for a public preview.
	 *
	 * @return array The post ids. (Empty array if no ids are registered.)
	 */
	private static function get_preview_post_ids() {
		return get_option( 'public_post_preview', [] );
	}

	/**
	 * Returns post statuses which represent a published post.
	 *
	 * @return array List with post statuses.
	 */
	private static function get_published_statuses() {
		$published_statuses = array( 'publish', 'private' );

		return apply_filters( 'ppp_published_statuses', $published_statuses );
	}

	/**
	 * Checks if a public preview is enabled for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return bool True if a public preview is enabled, false if not.
	 */
	public static function is_public_preview_enabled( $post ) {
	
		$preview_post_ids = self::get_preview_post_ids();
		return apply_filters( 'is_public_preview_enabled', in_array( $post->ID, $preview_post_ids, true ), $post );
	}

	/**
	 * Redirects to post's proper permalink, if it has gone live.
	 *
	 * @param int $post_id The post id.
	 * @return false False of post status is not a published status.
	 */
	private static function maybe_redirect_to_published_post( $post_id ) {
		if ( ! in_array( get_post_status( $post_id ), self::get_published_statuses() ) ) {
			return false;
		}

		wp_redirect( get_permalink( $post_id ), 301 );
		exit;
	}

	/**
	 * Get the time-dependent variable for nonce creation.
	 *
	 * @see wp_nonce_tick()
	 *
	 * @return int The time-dependent variable.
	 */
	private static function nonce_tick() {
		$nonce_life = apply_filters( 'ppp_nonce_life', 60 * 60 * 48 ); // 48 hours
		return ceil( time() / ( $nonce_life / 2 ) );
	}

	/**
	 * Verifies that correct nonce was used with time limit. Without an UID.
	 *
	 * @see wp_verify_nonce()
	 *
	 * @param string     $nonce  Nonce that was used in the form to verify.
	 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
	 * @return bool               Whether the nonce check passed or failed.
	 */
	private static function verify_nonce( $nonce, $action = -1 ) {
		$i = self::nonce_tick();

		// Nonce generated 0-12 hours ago.
		if ( substr( wp_hash( $i . $action, 'nonce' ), -12, 10 ) == $nonce ) {
			return 1;
		}

		// Nonce generated 12-24 hours ago.
		if ( substr( wp_hash( ( $i - 1 ) . $action, 'nonce' ), -12, 10 ) == $nonce ) {
			return 2;
		}

		// Invalid nonce.
		return false;
	}

	/**
	 * Checks if a public preview is available and allowed.
	 * Verifies the nonce and if the post id is registered for a public preview.
	 *
	 * @param int $post_id The post id.
	 * @return bool True if a public preview is allowed, false on a failure.
	 */
	private static function is_public_preview_available( $post_id ) {
		if ( empty( $post_id ) ) {
			return false;
		}

		if ( ! self::verify_nonce( get_query_var( '_ppp' ), 'public_post_preview_' . $post_id ) ) {
			wp_die( __( 'The link has been expired!', 'public-post-preview' ) );
		}

		if ( ! apply_filters( 'is_public_preview_available', in_array( $post_id, self::get_preview_post_ids() ), $post_id ) ) {
			wp_die( __( 'No public preview available!', 'public-post-preview' ) );
		}

		return true;
	}

	/**
	 * Sets the post status of the first post to publish, so we don't have to do anything
	 * *too* hacky to get it to load the preview.
	 *
	 * @param  array $posts The post to preview.
	 * @return array The post that is being previewed.
	 */
	public static function set_post_to_publish( $posts ) {
		// Remove the filter again, otherwise it will be applied to other queries too.
		remove_filter( 'posts_results', array( __CLASS__, 'set_post_to_publish' ), 10 );

		if ( empty( $posts ) ) {
			return;
		}

		$post_id = $posts[0]->ID;

		// If the post has gone live, redirect to it's proper permalink.
		self::maybe_redirect_to_published_post( $post_id );

		if ( self::is_public_preview_available( $post_id ) ) {
			// Set post status to publish so that it's visible.';
			$posts[0]->post_status = 'publish';

			// Disable comments and pings for this post.
			add_filter( 'comments_open', '__return_false' );
			add_filter( 'pings_open', '__return_false' );
			add_filter( 'wp_link_pages_link', [ get_parent_class(), 'filter_wp_link_pages_link' ], 10, 2 );
		}

		return $posts;
	}

	/**
	 * Registers the filter to handle a public preview.
	 *
	 * Filter will be set if it's the main query, a preview, a singular page
	 * and the query var `_ppp` exists.
	 *
	 * @param object $query The WP_Query object.
	 * @return object The WP_Query object, unchanged.
	 */
	public static function show_public_preview( $query ) {
		if (
			$query->is_main_query() &&
			$query->is_preview() &&
			$query->is_singular() &&
			$query->get( '_ppp' )
		) {
//			if ( ! headers_sent() ) {
//				nocache_headers();
//			}
//			add_action( 'wp_head', 'wp_no_robots' );
			remove_filter( 'posts_results', [ get_parent_class(), 'set_post_to_publish' ], 10 );
			add_filter( 'posts_results', [ __CLASS__, 'set_post_to_publish' ], 10, 1 );
		}

		return $query;
	}

	private static function is_valid_event( $event ) {
		$terms = get_the_terms( $event, Kursoversikt::$woo_event_tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$count = 0;
			foreach ( $terms as $term ) {
				$valid = $term->parent == Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ];
				if ( $valid ) {
					$count++;
				}
			}
			$has_term = $count == 1;
		} else {
			$has_term = false;
		}
		return $event->post_type == 'product' && $has_term;
	}

	public static function sub_init() {	// Must not be named init, as parent method then will be overridden!
		add_filter( 'is_public_preview_enabled', function( $enabled, $event ) {
			$enabled = $enabled || ( ! is_admin() && isset( $_GET['preview'] ) && isset( $_GET['_ppp'] ) && self::is_valid_event( $event ) );
			return $enabled;
		}, 10, 2 );

		add_filter( 'is_public_preview_available', function( $available, $event_id ) {
			$event = get_post( $event_id );
			$event_crit = $event->post_type == 'product';	// More crits!
			$page_crit  =
				$event->post_type == 'page' && (
					get_page_template_slug( $event_id ) == 'templates/oceanwp-calendar.php' ||
					has_shortcode( $event->post_content, 'kursoversikt' ) ||
					has_block( 'babyswim/kursoversikt', $event_id )
				)
			;
			$available = $available || $event_crit/* || $page_crit*/;
			return $available;
		}, 10, 2 );
		
		add_filter( 'ppp_published_statuses', function( $published_statuses ) {
			$published_statuses = array_diff( $published_statuses, ['private'] );
			return $published_statuses;
		} );

		add_filter( 'ppp_nonce_life', function( $nonce_life ) {
			$nonce_life = DAY_IN_SECONDS * Kursoversikt::$preview_life;
			return $nonce_life;
		} );
		
		if ( ! is_admin() ) {
			add_filter( 'pre_get_posts', [ __CLASS__, 'show_public_preview' ] );
		}

		add_filter( 'woocommerce_is_purchasable', function( $is_purchasable, $object ) {
			$is_purchasable = $object->get_price() > 0.;
			return $is_purchasable;
		}, 10, 2 );
	}
}

add_action( 'init', [ 'Webfacing_Public_Post_Preview', 'sub_init' ] );	// Must be later than the plugins_loaded hook.
