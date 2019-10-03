<?php
//namespace Babyswim\knutsp;

/**
 * Don't call this file directly.
 */
if ( ! class_exists( 'WP' ) ) {
	die();
}

class Kursoversikt {

	const pf               = 'webfacing_events_';					// Prefix for global functions and variables, template for self::$pf

	const day_periods      = [ 'natt', 'formid&shy;dag$', 'etter&shy;middag$', 'kveld$' ];

	const day_periods_off  = -3.;									// Hours offset for day_periods (add to hour before calc day_period key)

//	const seasons          = [ 'vinter', 'vår', 'sommer', 'høst' ];
	const seasons          = [ 'vår', 'høst' ];

	const event_tax        = 'product_cat';

	const loc_tax_suffix   = 'location';

	static $instructor_role  = [ 'instructor' => 'Instruktør' ];	// Set in __construct

	const WEEKS            = 9;										// Deprecated, use $event_times

	static $version        = '0';
	
	static $pf             = 'webfacing-events-';					// Prefix for slugs, array keys. Set in __construct

	static $woo_event_tax  = self::event_tax;						//

	static $woo_event_cat  = [ self::event_tax => 76 ];				// Set in __construct

	static $woo_loc_tax;

	static $use_tickera;

	static $event_times    = 9;		// Times (weeks)

	static $event_interval = 7;		// Days

	static $event_duration = 30;	// Minutes

	static $woo_auto_title = false;	// Auto title for events

	static $preview_life   = 3;		// Days

	static $woo_product_title;

	public static function get_plugin_data() {
		if ( function_exists( 'get_plugin_data' ) ) {
			return get_plugin_data( WEBFACING_EVENTS );
		}
	}

	public static function get_version() {
		return self::get_plugin_data()['Version'];
	}

	public static function use_tickera() {
		return self::$use_tickera;
	}

	/**
	 * Get event duration in decimal hours, like 0.5.
	 */ 
	public static function get_event_duration(): float {
		return self::$event_duration * MINUTE_IN_SECONDS / HOUR_IN_SECONDS;
	}
	
	/**
	 * Formats a display time string (1630) based on $hour as float, like 16.5.
	 */ 
	public static function fmt_display_time( float $hour ): string {
		$ihour = intval( $hour );
		$frac = $hour - floatval( $ihour );
		return str_pad( $ihour, 2, '0', STR_PAD_LEFT ) . ':' . str_pad( intval( $frac * HOUR_IN_SECONDS / MINUTE_IN_SECONDS ), 2, '0', STR_PAD_LEFT );
	}

	public static function get_minutes_from_time( string $time ) {
		return ( intval( substr( $time, 0, 2 ) ) * HOUR_IN_SECONDS / MINUTE_IN_SECONDS ) + intval( substr( $time, 3, 2 ) ) ;
	}

	/**
	 * Returns unix time from event/product
	 */
	public static function get_event_start_time( $post ): int {
		$post_id = is_object( $post ) ? $post->ID : $post;
		$meta   = Kursoversikt_Settings::$event_meta;
		$names  = array_keys( $meta );
		return mysql2date( 'U', get_post_meta( $post_id, $names[0], true ) . ' ' . get_post_meta( $post_id, $names[1], true ) );
	}

	/**
	 * Returns unix time for earliest event to consider in listing
	 */
	public static function get_events_from(): int {
		return current_time( 'U' ) - ( ( self::$event_times + 1 ) * self::$event_interval * DAY_IN_SECONDS );
	}

	public static function woo_render() {
		global $post, $weekday;
		include( 'kursoversikt-woo-render.php' );
	}

	public static function tickera_render() {
		global $post, $weekday;
		include( 'kursoversikt-tickera-render.php' );
	}

	public static function return_woo_render() {
		global $post, $weekday;
		ob_start();
		include( 'kursoversikt-woo_render.php' );
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	public static function return_tickera_render() {
		global $post, $weekday;
		ob_start();
		include( 'kursoversikt-tickera-render.php' );
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	public static function block() {
		wp_register_script( 'block-kursoversikt', plugins_url( 'block.js', __FILE__ ), [ 'wp-blocks', 'wp-element', 'wp-components' ], self::get_version() );
		register_block_type( 'babyswim/kursoversikt', [
			'style' => 'kursoversikt',
			'editor_script' => 'block-kursoversikt',
			'render_callback' => self::use_tickera() ? 'self::return_tickera_render' : 'return_woo_render',
		] );
		if ( is_page_template( 'kursoversit.php' ) ) {
			wp_register_script( 'kursoversikt', plugins_url( 'kursoversikt.js',  __FILE__ ), [], self::get_version() );
			wp_register_style(  'kursoversikt', plugins_url( 'kursoversikt.css', __FILE__ ), [], self::get_version() );
		}
	}

	protected function register_meta() {
		$meta = Kursoversikt_Settings::$event_meta;
		foreach ( $meta as $meta_key => $field_def ) {
			if ( ( isset( $field_def['value_type'] ) && $field_def['value_type'] == 'number' ) ||
				 ( isset( $field_def['type'      ] ) && $field_def['type'      ] == 'number' )
			) {
				if (
					( isset( $field_def['step'] ) && intval( $field_def['step'] ) == floatval( $field_def['step'] ) ) ||
					! isset( $field_def['step'] ) )
				{
					$sanitize_callback = 'intval';
					$type              = 'integer';
				} else {
					$sanitize_callback = 'floatval';
					$type              = 'string';
				}
			} elseif ( isset( $field_def['value_type'] ) && $field_def['value_type'] == 'array' ) {
				$sanitize_callback = function( $value ) { return is_array( $value ) ? $value : [ $value ]; };
				$type              = 'string';
			} else {
				$sanitize_callback = 'sanitize_text_field';
				$type              = 'string';
			}
			$sanitize_callback = isset( $field_def['sanitize_callback'] ) ? $field_def['sanitize_callback'] : $sanitize_callback;
			$auth_callback     = isset( $field_def['auth_callback'    ] ) ? $field_def['auth_callback'    ] : null;
			$args = [
				'sanitize_callback' => $sanitize_callback,
				'auth_callback'     => $auth_callback,
				'type'              => $type,
				'description'       => $field_def['label'],
				'single'            => true,
				'show_in_rest'      => false,
			];
			register_post_meta( 'product', $meta_key, $args );
		}
	}

	public function init() {
		
		$settings = get_option( Kursoversikt::pf . 'settings' );
		if ( ! empty( $settings['event-times'] ) ) {
			self::$event_times = intval( $settings['event-times'] );
		}
		
		if ( ! empty( $settings['event-interval'] ) ) {
			self::$event_interval = intval( $settings['event-interval'] );
		}

		if ( ! empty( $settings['event-duration'] ) ) {
			self::$event_duration = self::get_minutes_from_time( $settings['event-duration'] );
		}

		if ( ! empty( $settings['auto-title'] ) ) {
			self::$woo_auto_title = boolval( $settings['auto-title'] );
		}

		if ( ! empty( $settings['event-cat'] ) ) {
			self::$woo_event_cat[ self::$woo_event_tax ] = intval( $settings['event-cat'] );
		}

		if( ! empty( $settings['preview-life'] ) ) {
			self::$preview_life = intval( $settings['preview-life'] );
		}

		Kursoversikt::$use_tickera = is_plugin_active( 'bridge-for-woocommerce/bridge-for-woocommerce.php' );

	}

	/**
	 * Removes the password protected option on post.php classic editor
	 */
	public static function remove_password_protected_option() {
		$screen = get_current_screen();
		if ( in_array( $screen->base, [ 'edit', 'post' ] ) && ! in_array( $screen->post_type, [] ) ) {
?>
		<style>
			input#visibility-radio-password,
			label[for="visibility-radio-password"],
			label[for="visibility-radio-password"] ~ br,
			.inline-editor label.alignleft:first-child,
			.inline-editor label.alignleft ~ em {
				display: none;
			}
		</style>
<?php
		}
	}

	public static function set_virtual() {
?>
		<script>
			( function( $ ) {
				$( 'input[name=_virtual]').prop( 'checked', true );
			} )( jQuery );
		</script>
<?php
}

	public static function make_processing_orders_editable( $is_editable, $order ) {
		if ( $order->get_status() == 'processing' ) {
			$is_editable = true;
		}
		return $is_editable;
	}

	public static function wp_loaded() {

		$label = get_post_type_object( 'shop_order' )->labels;
		$label->menu_name          = 'Påmeldinger';						// Dosn't work

		$label = get_post_type_object( 'product' )->labels;
		$label->name               = 'Kurs';
		$label->singular_name      = 'Kurs';
		$label->menu_name          = 'Kurs';
		$label->add_new            = 'Legg til nytt';
		$label->add_new_item       = 'Legg til nytt kurs';
		$label->all_items          = 'Alle kurs';
		$label->edit_item          = 'Rediger kurs';
		$label->name_admin_bar     = 'Kurs';
		$label->new_item           = 'Nytt kurs';
		$label->not_found          = 'Ingen kurs funnet';
		$label->not_found_in_trash = 'Ingen kurs funnet i papirkurven';
		$label->search_items       = 'Søk etter kurs';
		$label->view_item          = 'Vis kurset';
		$label->item_updated       = 'Kurset oppdatert';
		$label->item_published     = 'Kurset publisert';

		$tax   = get_taxonomy( 'product_cat' );
		$tax->label                        = 'Aldersgrupper';
		$label = $tax->labels;
		$label->name                       = 'Aldersgrupper';
		$label->menu_name                  = 'Aldersgrupper';
		$label->singular_name              = 'Aldersgruppe';
		$label->search_items               = 'Søk etter aldersgruppe';
		$label->popular_items              = 'Mest brukte aldersgrupper';
		$label->all_items                  = 'Alle aldersgruppeer';
		$label->edit_item                  = 'Rediger aldersgruppe';
		$label->add_new_item               = 'Legg til ny aldersgruppe';
		$label->new_item_name              = 'Nytt navn på aldersgruppen';
		$label->parent_item                = 'Kurskategori';
		$label->parent_item_colon          = 'Kurskategori:';
		$label->update_item                = 'Oppdater aldersgruppen';
		$label->add_or_remove_items        = 'Legg til eller fjern aldersgruppe';
		$label->back_to_items              = 'Tilbake til Aldersgrupper';
		$label->item_updated               = 'Aldersgruppen er oppdatert.';

		
		add_filter( 'gettext', function( $trans, $text, $domain ) {
			if ( $domain == 'woocommerce-admin' ) {
				if ( $trans == 'Produkter' ) {
					$trans = 'Kurs';
				}
			} elseif ( $domain == 'woocommerce' ) {
				if ( $trans == 'Produkt' ) {
					$trans = 'Kurs';
				} elseif ( $trans == 'Kategorier' ) {
					$trans = 'Aldersgruppe';
				} elseif ( $trans == 'Filtrer på lagerstatus' ) {
					$trans = 'Filtrer på ledige';
				} elseif ( $trans == '%s på lager' ) {
					$trans = '%s ledige plasser' ;
				} elseif ( $trans == 'Velg kategori' ) {
					$trans = 'Velg aldersgruppe';
				} elseif ( $trans == 'Lager' ) {
					$trans = 'Ledighet';
				} elseif ( $trans == 'Lagerstatus' ) {
					$trans = 'Ledighet';
				} elseif ( $trans == 'Enheter på lager' ) {
					$trans = 'Ledige plasser';
				} elseif ( $trans == 'På lager' ) {
					$trans = 'Ledige	';
				} elseif ( $trans == 'Lite på lager' ) {
					$trans = 'Få ledige';
				} elseif ( $trans == 'Tomt på lager' ) {
					$trans = 'Ingen ledige';
				} elseif ( $trans == 'Mest på lager' ) {
					$trans = 'Flest ledige';
				} elseif ( $text == 'Place order' ) {
					$trans = 'Send påmelding';
				} elseif ( $text == 'Order' ) {
					$trans = 'Påmelding';
				} elseif ( $text == 'Orders' ) {
					$trans = 'Påmeldinger';
				} elseif ( $trans == 'Ingen ordre har blitt gjort enda.' ) {
					$trans = 'Ingen påmeldinger enda.';
				} elseif ( $trans == 'Gå til butikken' ) {
					$trans = 'Gå til kursoversikten';
				} elseif ( $trans == 'Opprett en konto?' ) {
					$trans = 'Ønsker du å opprette en konto?';
				} elseif ( $text == 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.' ) {
					$trans = 'Fra ditt kontrollpanel kan du se dine <a href="%1$s">siste påmeldinger</a>, redigere din <a href="%2$s">fakturaadresse</a>, <a href="%3$s">dine kontodetaljer og endre passord</a>.';
				}
			} elseif ( $domain == 'oceanwp' ) {
				if ( $trans == 'Fakturering' && is_checkout() ) {
					$trans = 'Forelder';
				} elseif ( $trans == 'Frakt' && is_checkout() ) {
					$trans = 'Barn';
				} elseif ( $trans == 'Logg inn' && is_checkout() ) {
					$trans = 'Logge inn?';
				} elseif ( $trans == 'Om du allerede har registrert deg, vennligst oppgi kontodetaljene i feltene nedenfor. Er du en ny kunde, vennligst gå til seksjonen Fakturering.' && is_checkout() ) {
					$trans = 'Om du allerede har registrert deg, vennligst oppgi kontodetaljene i feltene nedenfor. Er du en ny kunde, vennligst trykk på knappen "Jeg har ikke en konto".';
				}
			} elseif ( $domain == 'email-log' ) {
				if ( $trans == 'E-poster' ) {
					$trans = 'E-poster/SMS';
				} elseif ( $text == 'Email Logs' ) {
					$trans = 'E-post/SMS-logg';
				}
			}
			return $trans;
		}, 10, 3 );
		
		add_filter( 'ngettext', function( $trans, $single, $plural, $number, $domain ) {
			if ( $domain == 'woocommerce' ) {
				if ( $single == '%1$s for %2$s item' && $plural == '%1$s for %2$s items' ) {
					$trans = '%1$s for %2$s kurs';
				} elseif ( $single == '<strong>%s product</strong> out of stock' && $plural == '<strong>%s products</strong> out of stock' ) {
					$trans = $number == 0 ? '<strong>Ingen publ. kurs</strong> fulltegnet' : '<strong>%s publ. kurs</strong> fulltegnet';
				} elseif ( $single == '<strong>%s product</strong> low in stock' && $plural == '<strong>%s products</strong> low in stock' ) {
					$trans = $number == 0 ? '<strong>Ingen publ. kurs</strong> med få plasser igjen' : '<strong>%s publ. kurs</strong> med få plasser igjen';
				} elseif ( $single == '<strong>%s order</strong> on-hold' && $plural == '<strong>%s orders</strong> on-hold' ) {
					$trans = $number == 1 ? '<strong>%s påmelding</strong> med få plasser igjen' : ( $number == 0 ? '<strong>Ingen påmeldinger</strong> med få plasser igjen' : '<strong>%s påmeldinger</strong> med få plasser igjen' );
				} elseif ( $single == '<strong>%s order</strong> awaiting processing' && $plural == '<strong>%s orders</strong> awaiting processing' ) {
					$trans = $number == 1 ? '<strong>%s påmelding</strong> venter på behandling' : ( $number == 0 ? '<strong>Ingen påmeldinger</strong> venter på behandling' : '<strong>%s påmeldinger</strong> venter på behandling' );
				}
			}
			return $trans;
		}, 10, 5);
		
		add_filter ( 'woocommerce_account_menu_items', function( $menu ) {
			unset ( $menu['downloads'] );
			return $menu;
		} );
	}
	
	public static function plugins_loaded() {

		self::$version = self::get_plugin_data()['Version'];

		if ( class_exists( 'DS_Public_Post_Preview' ) ) {
			require_once( 'class-webfacing-public-post-preview.php' );
		}

		add_action( 'admin_head', [ __CLASS__, 'remove_password_protected_option' ] );
	}

	public static function admin_init() {
		self::$woo_product_title = get_transient( 'enter_title_here' );
	}

	public function __construct() {
		self::$pf = str_replace( '_', '-', self::pf );
		self::$woo_loc_tax = self::$pf . self::loc_tax_suffix;
		self::$instructor_role = [ 'instructor' => 'Instruktør' ];

		add_action( 'init', [ __CLASS__, 'block' ] );
		add_action( 'init', [ $this, 'init' ] );
		
		add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'set_virtual' ] );
		add_filter( 'wc_order_is_editable', [ __CLASS__, 'make_processing_orders_editable' ], 10, 2 );
		add_filter( 'enter_title_here', function( $title ) {
			set_transient( 'enter_title_here', $title );
//			self::$woo_product_title = $title;
			return $title;
		} );
		add_action( 'admin_init', [ __CLASS__, 'admin_init' ] );
		add_action( 'wp_loaded',  [ __CLASS__, 'wp_loaded'  ] );
	}

	public static function install() {
		foreach ( self::$instructor_role as $role => $display_name ) {
			add_role( $role, $display_name, [ 'read', 'edit_posts', 'upload_files', 'read_participants' ] );
		}
	}
}

// Initialize static properties and load subclasses of other plugins
add_action( 'plugins_loaded', [ 'Kursoversikt', 'plugins_loaded' ] );
