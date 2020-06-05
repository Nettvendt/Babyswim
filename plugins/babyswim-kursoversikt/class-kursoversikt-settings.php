<?php
//namespace Babyswim\knutsp;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 */
class Kursoversikt_Settings {

	public static $event_meta;

	public $settings;

	public static $page;
	
	public static $sections;

	public function calendar_page_link( bool $published = false, bool $link = true ): string {
		$html = '';
		$pending_pages = get_posts( [ 'post_type' => 'page', 'post_status' => $published ? [ 'publish' ] : [ 'pending', 'future'], 'posts_per_page' => 99 ] );
		foreach ( $pending_pages as $page ) {
			$page_id = intval( $page->ID );
			if ( get_page_template_slug( $page_id ) == 'templates/oceanwp-calendar.php' || has_shortcode( $page->post_content, 'kursoversikt' ) || has_block( 'babyswim/kursoversikt', $page_id ) ) {
				if ( $link ) {
					$html .= PHP_EOL . '<a href="' . get_the_permalink( $page ) . '" title="Vis siden.">' . get_the_title( $page ) . '</a>';
					if ( current_user_can( 'edit_page', $page_id ) ) {
						$html .= ' <a href="' . get_edit_post_link( $page ) . '" title="Rediger siden.">(rediger)'. '</a>';
					}
				} else {
					$html .= PHP_EOL . get_the_title( $page );
				}

			}
		}
		return $html;
	}
	
	public function select( $args ) {
		$opts  = get_option( Kursoversikt::pf . 'settings' );
		$id    = esc_attr( $args['label_for'] );
		$unit  = esc_attr( $args['unit'] );
		$value = isset( $opts[ $id ] ) ? intval( $opts[ $id ] ) : ( isset( $args['default'] ) ? intval( $args['default'] ) : false );
		if ( $args['object'] == 'terms' ) {
			$term = $value ? get_term( $value, $args[ $args['object']]['taxonomy'] ) : false;
			$term_name = $term ? $term->name : false;
			$term_slug = $term ? $term->slug : false;
	?>
			<select id="<?=$id?>" name="<?=Kursoversikt::pf?>settings[<?=$id?>]" title="<?=$value?>: <?=$term_slug?>.">
	<?php
			$terms = get_terms( [
				'object_id'  => $args[ $args['object']]['object_id'],
				'taxonomy'   => $args[ $args['object']]['taxonomy'],
				'parent'     => 0,
				'hide_empty' => false,
			] );
			
			foreach ( $terms as $term ) {
	?>
				<option value="<?=$term->term_id?>"<?=selected($term->term_id,$value,false)?> title="<?=$term->term_id?>: <?=$term->slug?>."><?=$term->name?></option>
	<?php
			}
		} elseif ( $args['object'] == 'posts' ) {
			$posts = get_posts( [ 'post_type' => $args[ $args['object']['oject_id']]] );
		} else {
			echo 'Object ', $args['object'], ' not supported!';
		}
?>
		</select> <?=$unit?>
<?php
	}

	public function checkbox( $args ) {
		$opts  = get_option( Kursoversikt::pf . 'settings' );
		$id    = isset( $args['label_for'] ) ? esc_attr( $args['label_for'] ) : false;
		$type  = isset( $args['type'     ] ) ? esc_attr( $args['type'     ] ) : 'checkbox';
		$unit  = isset( $args['unit'     ] ) ? esc_attr( $args['unit'     ] ) : false;
		$value = boolval( isset( $opts[ $id ] ) ? $opts[ $id ] : ( isset( $args['default'] ) ? $args['default'] : false ) );
?>
			<input id="<?=$id?>" type="<?=$type?>" name="<?=Kursoversikt::pf?>settings[<?=$id?>]"<?=checked($value,true,false)?> /> <?=$unit?>
<?php
	}

	public function input( $args ) {
		$opts  = get_option( Kursoversikt::pf . 'settings' );
		$id    = isset( $args['label_for'] ) ? esc_attr( $args['label_for'] ) : false;
		$type  = isset( $args['type'     ] ) ? esc_attr( $args['type'     ] ) : false;
		$unit  = isset( $args['unit'     ] ) ? wp_kses( $args['unit' ], [ 'a' => [ 'href' => [], 'title' => [] ] ] ) : false;
		$min   = isset( $args['min'      ] ) ? esc_attr( $args['min'      ] ) : false;
		$max   = isset( $args['max'      ] ) ? esc_attr( $args['max'      ] ) : false;
		$step  = isset( $args['step'     ] ) ? esc_attr( $args['step'     ] ) : false;
		$width = isset( $args['width'    ] ) ? esc_attr( $args['width'    ] ) : ( $max ? strlen( $max ) + 2 : false );
		$align = $type == 'number' ? 'text-align:right;' : false;
		$value = isset( $opts[ $id ] ) ? $opts[ $id ] : ( isset( $args['default'] ) ? $args['default'] : false );
?>
			<input id="<?=$id?>" type="<?=$type?>" min="<?=$min?>" max="<?=$max?>" step="<?=$step?>" name="<?=Kursoversikt::pf?>settings[<?=$id?>]" value="<?=$value?>" style="width: <?=$width?>em;<?=$align?>" /> <?=$unit?>
<?php
	}

	public function section( $args ) {
		$id    = esc_attr( $args['id'   ] );
		$title = esc_attr( $args['title'] );
?>
			<p id="<?=$id?>"><?=$title?> kan gjøres her.</p>
<?php
	}

	public function page() {
		if ( current_user_can( 'edit_shop_orders' ) ) {
//			if ( isset( $_GET['settings-updated'] ) ) {
//				add_settings_error( 'kursoversikt_messages', 'kursoversikt_message', 'Innstillinger lagret', 'updated' );
//			}
			settings_errors( Kursoversikt::$pf . 'messages' );
			$title = get_admin_page_title();
?>
	<div class="wrap">
		<h1><?=$title?></h1>
		<form action="options.php" method="post">
<?php
			settings_fields( self::$page );			// Hidden fields
			do_settings_sections( self::$page );	// The registered sections
			submit_button( __( 'Save Changes' ) );
 ?>
		</form>
	</div>
 <?php
		}
	}
	
	public function menu() {
		add_options_page( 'Kursoversikt', 'Kursoversikt', 'manage_options', self::$page, [ $this, 'page' ] );
	}

	public function init() {

		$this->settings = get_option( Kursoversikt::pf . 'settings' );

		register_setting( self::$page, Kursoversikt::pf . 'settings' );		// Group and option name to use
		
		add_settings_section( self::$sections[0], 'Innstillinger for kurskategori', [ $this, 'section' ], self::$page );

		$args = [
			'label_for' => 'event-cat',
			'object'    => 'terms',	// Only terms supports yet!
			'terms'		=> [
				'object_id' => 'product',
				'taxonomy'  => Kursoversikt::$woo_event_tax,
			],
			'label'     => get_taxonomy_labels( get_taxonomy( Kursoversikt::$woo_event_tax ) )->singular_name,
			'default'   => 76,
			'unit'      => Kursoversikt::$woo_event_tax,
		];
		add_settings_field( $args['label_for'], $args['label'], [ $this, 'select' ], self::$page, self::$sections[0], $args );

		$args = [
			'label_for' => 'auto-title',
			'type'      => 'checkbox',
			'label'     => 'Automatisk ' . strtolower( Kursoversikt::$woo_product_title ),
			'unit'      => 'på/av',
		];
		add_settings_field( $args['label_for'], $args['label'], [ $this, 'checkbox' ], self::$page, self::$sections[0], $args );

//		$args = [
//			'label_for' => 'auto-content',
//			'type'      => 'checkbox',
//			'label'     => 'Automatisk innhold',
//			'unit'      => 'på/av',
//		];
//		add_settings_field( $args['label_for'], $args['label'], [ $this, 'checkbox' ], self::$page, self::$sections[0], $args );
//
//		$args = [
//			'label_for' => 'auto-excerpt',
//			'type'      => 'checkbox',
//			'label'     => 'Automatisk utdrag',
//			'unit'      => 'på/av',
//		];
//		add_settings_field( $args['label_for'], $args['label'], [ $this, 'checkbox' ], self::$page, self::$sections[0], $args );

		add_settings_section( self::$sections[1], 'Innstillinger for standard kursperiode', [ $this, 'section' ], self::$page );
		
		$args = [
			'label_for' => 'event-times',
			'type'      => 'number',
			'label'     => 'Antall',
			'default'   => 9,
			'min'       => 1,
			'max'       => 52,
			'unit'      => 'ganger',
		];
		add_settings_field( $args['label_for'], $args['label'], [ $this, 'input' ], self::$page, self::$sections[1], $args );
		
		$args = [
			'label_for' => 'event-interval',
			'type'      => 'number',
			'label'     => 'Intervall',
			'default'   => 7,				// One week (every week)
			'unit'      => 'dager',
			'min'       => 1,
			'max'       => 99,
			'step'      => 1,
		];
		add_settings_field( 'event-interval', $args['label'], [ $this, 'input' ], self::$page, self::$sections[1], $args );

		$args = [
			'label_for' => 'event-duration',
			'type'      => 'time',
			'label'     => 'Varighet',
			'default'   => '00:30',			// Half an hour
			'unit'      => 'tt:mm',
			'min'       => '00:10',
			'max'       => '04:30',
			'step'      => '1:00',
		];
		add_settings_field( $args['label_for'], $args['label'], [ $this, 'input' ], self::$page, self::$sections[1], $args );

		add_settings_section( self::$sections[2], 'Innstillinger for forhåndspåmelding', [ $this, 'section' ], self::$page );

//		$args = [
//			'label_for' => 'preview-life',
//			'type'      => 'number',
//			'label'     => 'Varighet lenker til forhåndsvisning',
//			'unit'      => 'dager',
//			'min'       => 1,
//			'max'       => Kursoversikt::$event_times * Kursoversikt::$event_interval,
//		];
//		add_settings_field( $args['label_for'], $args['label'], [ $this, 'input' ], self::$page, self::$sections[2], $args );

		$args = [
			'label_for' => 'preview-links-date',
			'type'      => 'date',
			'label'     => /*$this->calendar_page_link( true, false ) . ' og ' . */$this->calendar_page_link( false, false ) . ' åpner dato',
			'min'       => wp_date( 'c' ),
			'max'       => wp_date( 'c', strtotime( '+' . Kursoversikt::$event_times * Kursoversikt::$event_interval . ' days' ) ),
			'width'     => 9,
			'unit'      => /*$this->calendar_page_link( true ) . ' | ' . */$this->calendar_page_link(),
		];
		add_settings_field( $args['label_for'], $args['label'], [ $this, 'input' ], self::$page, self::$sections[2], $args );

		$args = [
			'label_for' => 'preview-links-time',
			'type'      => 'time',
			'label'     => /*$this->calendar_page_link( true, false ) . ' og ' . */$this->calendar_page_link( false, false ) . ' åpner kl',
			'unit'      => 'tt:mm',
			'min'       => '00:00',
			'max'       => '23:50',
			'step'      => '1:00',
		];
		add_settings_field( $args['label_for'], $args['label'], [ $this, 'input' ], self::$page, self::$sections[2], $args );

		set_transient ( Kursoversikt::pf .'settings', $GLOBALS['wp_settings_fields' ][ self::$page ] );
}
	
	public static function get_settings(): array {
		$fields = [];
//		$page   = $GLOBALS['wp_settings_fields'  ][ self::$page ] ?? false;
		$page   = get_transient ( Kursoversikt::pf .'settings' );
		foreach ( $page as $section_name => $section ) {
			$fields[ $section_name . '-head'] = [
				'name' => $sections[ $section_name ]['title'],
				'type' => 'title',
				'id' => $section_name,
			];
			foreach ( $section as $field_name => $field ) {
				$fields[ $field_name ] = [
					'name' => $field['args']['label'],
					'type' => $field['callback'][1],
					'id'   => Kursoversikt::pf .'settings[' . $field_name . ']',
				];
				if ( $field['args']['object'] === 'terms' ) {
					$fields[ $field_name ]['options'] = get_terms( [
						'object_id'  => $field['args']['terms']['object_id'],
						'taxonomy'   => $field['args']['terms']['taxonomy'],
						'parent'     => 0,
						'hide_empty' => false,
						'fields'     => 'id=>name',
					] );
				} elseif ( $field['args']['object'] === 'attribute_taxonomies' ) {
					$fields[ $field_name ]['options'] = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
				}
			}
			$fields[ $section_name . '-footer'] = [
				'type' => 'sectionend',
				'id' => $section_name . '-end',
			];
		}
		return $fields;
	}

	public function __construct() {
		
		self::$page     = Kursoversikt::$pf .'page';
		self::$sections = [ Kursoversikt::$pf . 'category', Kursoversikt::$pf . 'period', Kursoversikt::$pf . 'preview' ];

		self::$event_meta = [
			Kursoversikt::pf . 'start'      => [ 'fieldset' => [ 'kurstart-set' => 'Kursstart' ],
				'label_for' => 'start', 'type' => 'date', 'label' => 'Kursstart dato', 'unit' => '&nbsp;' ],
			Kursoversikt::pf . 'time'       => [ 'fieldset' => [ 'kurstart-set' => 'Kursstart' ],
				'label_for' => 'time',  'type' => 'time', 'label' => 'Kursstart kl', 'size' => 5, 'min' => '07:00', 'max' => '23:50' ],
			Kursoversikt::pf . 'instructor' => [ 'fieldset' => [ 'instructor-set' => 'Instruktør' ],
				'label_for' => 'instructor', 'type' => 'select', 'value_type' => 'number', 'label' => 'Instruktør',
				'options'   => [ 'users' => [ 'role' => array_key_first( Kursoversikt::$instructor_role ) ],
					'option'  => [ 'value' => 'ID', 'label' => 'display_name' ],
				],
			],
		];
		
		add_action( 'admin_init', [ $this, 'init' ] );
		
		add_action( 'admin_menu', [ $this, 'menu' ] );

		add_filter( 'woocommerce_settings_tabs_array', function( array $settings_tabs ): array {
			$settings_tabs[ Kursoversikt::pf . 'settings' ] = mb_str_replace( ' for WooCommerce', '', Kursoversikt::get_plugin_data()['PluginName'] );
			return $settings_tabs;
		}, 40 );

		add_action( 'woocommerce_settings_tabs_'  . Kursoversikt::pf . 'settings', function() {
			woocommerce_admin_fields( self::get_settings() );
		} );

		add_action( 'woocommerce_update_options_' . Kursoversikt::pf . 'settings', function() {
			woocommerce_update_options( self::get_settings() );
			delete_transient ( Kursoversikt::pf . 'settings' );
		} );

		if ( WP_DEBUG ) {
			
			$this->settings = get_option( Kursoversikt::pf . 'settings', [] );

			add_action( 'admin_notices', function() { ?>
			<div class="notice notice-error is-dismissible">
				<p><strong style="color: darkred;"><?=get_bloginfo()?> er i feilsøkingsmodus for utvikling, noe det ikke skal være under produksjon. Kontakt <a href="mailto:<?=get_bloginfo('admin_email')?>"><?=get_user_by('email',get_bloginfo('admin_email'))->display_name??'administrator'?></a> straks eller <a href="tools.php?page=wp-local-dev-master">slå av hefra</a>.</strong><br />
					<?php echo Kursoversikt::get_plugin_data()['Name']; ?><br />
					<?php echo plugin_basename( WEBFACING_EVENTS ); ?><br />
					<?php echo WEBFACING_EVENTS; ?><br />
					<!--?php echo self::$domain_path, self::$text_domain, '-', \get_user_locale( \get_current_user_id() ), '.mo'; ?><br /-->
					<?php echo __FILE__; ?><br />
				</p>
					<?php swim_export( Kursoversikt::pf . 'settings' ); swim_export( $this->settings ); ?>
			</div>
<?php
			} );
		}
	}
}
